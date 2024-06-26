<?php
namespace FormalTheory\RegularExpression;

use FormalTheory\RegularExpression\Token\Special;
use FormalTheory\RegularExpression\Token\Repeat;
use FormalTheory\RegularExpression\Exception\LexException;
use FormalTheory\RegularExpression\Token\Set;
use FormalTheory\RegularExpression\Token\Constant;
use FormalTheory\RegularExpression\Token\Regex;
use FormalTheory\RegularExpression\Token\Union;

class Lexer
{

    private $_regex_pieces;

    private $_current_offset;

    function lex($regex_string)
    {
        if ($regex_string === "") {
            return new Regex(array(), FALSE);
        }
        $this->_regex_pieces = str_split($regex_string);
        $this->_current_offset = 0;
        $output = $this->_lex(FALSE, TRUE);
        if ($output instanceof Union) {
            $output = new Regex(array(
                $output
            ), FALSE);
        }
        if ($this->_current_offset !== count($this->_regex_pieces))
            throw new LexException("unexpected end");
        
        return $output;
    }

    private function _lex($in_group = FALSE, $is_outer = FALSE)
    {
        $last_is_escape = FALSE;
        $tokens = array();
        if ($this->_current_offset >= count($this->_regex_pieces)) {
            throw new LexException("unexpected end");
        }
        for (; $this->_current_offset < count($this->_regex_pieces); $this->_current_offset ++) {
            $current_piece = $this->_regex_pieces[$this->_current_offset];
            if (! $last_is_escape) {
                switch ($current_piece) {
                    case '\\':
                        $last_is_escape = TRUE;
                        break;
                    case '(':
                        $this->_current_offset ++;
                        $tokens[] = $this->_lex(TRUE);
                        $this->_current_offset --;
                        break;
                    case ')':
                        if (! $in_group) {
                            throw new LexException("unexpected symbol ')'");
                        }
                        $this->_current_offset ++;
                        $in_group = FALSE;
                        break 2;
                    case '[':
                        $this->_current_offset ++;
                        $tokens[] = $this->_lex_set();
                        break;
                    case '{':
                        $this->_current_offset ++;
                        $repeat_info = $this->_lex_repeat();
                        if ($repeat_info) {
                            list ($min, $max) = $repeat_info;
                            self::_isReadyForRepeat($tokens);
                            $tokens[] = new Repeat(array_pop($tokens), $min, $max);
                        } else {
                            $tokens[] = new Constant('{');
                        }
                        $this->_current_offset --;
                        break;
                    case '.':
                        $tokens[] = new Set(array(
                            "\n"
                        ), FALSE);
                        break;
                    case '*':
                        self::_isReadyForRepeat($tokens);
                        $tokens[] = new Repeat(array_pop($tokens), 0, NULL);
                        break;
                    case '+':
                        self::_isReadyForRepeat($tokens);
                        $tokens[] = new Repeat(array_pop($tokens), 1, NULL);
                        break;
                    case '?':
                        self::_isReadyForRepeat($tokens);
                        $tokens[] = new Repeat(array_pop($tokens), 0, 1);
                        break;
                    case '^':
                    case '$':
                        $tokens[] = new Special($current_piece);
                        break;
                    case '|':
                        $tokens[] = "|";
                        break;
                    default:
                        $tokens[] = new Constant($current_piece);
                        break;
                }
            } else {
                switch ($current_piece) {
                    case "w":
                    case "W":
                    case "d":
                    case "D":
                    case "s":
                    case "S":
                        $tokens[] = Set::newFromGroupChar($current_piece);
                        break;
                    case "t":
                    case "v":
                    case "r":
                    case "n":
                        $lookup = array(
                            "t" => "\t",
                            "v" => "\v",
                            "r" => "\r",
                            "n" => "\n"
                        );
                        $tokens[] = new Constant($lookup[$current_piece]);
                        break;
                    case "x":
                        $tokens[] = new Constant($this->_lex_hex());
                        break;
                    case "b":
                    case "B":
                        throw new \RuntimeException("not implemented");
                    default:
                        $tokens[] = new Constant($current_piece);
                        break;
                }
                $last_is_escape = FALSE;
            }
        }
        
        if ($in_group) {
            throw new LexException("unexpected end");
        }
        
        if (in_array('|', $tokens, TRUE)) {
            $regex_array = array();
            while (($offset = array_search('|', $tokens, TRUE)) !== FALSE) {
                $current_tokens = array_splice($tokens, 0, $offset + 1);
                array_pop($current_tokens);
                $regex_array[] = new Regex($current_tokens, TRUE);
            }
            $regex_array[] = new Regex($tokens, TRUE);
            return new Union($regex_array);
        } else {
            return new Regex($tokens, ! $is_outer);
        }
    }

    private function _lex_hex()
    {
        if ($this->_current_offset + 2 >= count($this->_regex_pieces)) {
            throw new LexException("unexpected end");
        }
        
        if (! ctype_xdigit($this->_regex_pieces[$this->_current_offset + 1])) {
            throw new LexException("unexpected non-hex character: " . $this->_regex_pieces[$this->_current_offset + 1]);
        }
        if (! ctype_xdigit($this->_regex_pieces[$this->_current_offset + 2])) {
            throw new LexException("unexpected non-hex character: " . $this->_regex_pieces[$this->_current_offset + 2]);
        }
        
        $symbol = chr(hexdec($this->_regex_pieces[$this->_current_offset + 1] . $this->_regex_pieces[$this->_current_offset + 2]));
        $this->_current_offset += 2;
        return $symbol;
    }

    private function _lex_set()
    {
        if ($this->_current_offset >= count($this->_regex_pieces)) {
            throw new LexException("unexpectedly found end while in set");
        }
        $is_negative = $this->_regex_pieces[$this->_current_offset] === "^";
        if ($is_negative)
            $this->_current_offset ++;
        $tokens = array();
        $last_is_escape = FALSE;
        $current_piece = NULL;
        for (; $this->_current_offset < count($this->_regex_pieces); $this->_current_offset ++) {
            $current_piece = $this->_regex_pieces[$this->_current_offset];
            if (! $last_is_escape) {
                switch ($current_piece) {
                    case '\\':
                        $last_is_escape = TRUE;
                        break;
                    case '-':
                        $tokens[] = NULL;
                        break;
                    case ']':
                        break 2;
                    default:
                        $tokens[] = $current_piece;
                        break;
                }
            } else {
                $tokens[] = $this->_lex_set_getEscaped($current_piece);
                $last_is_escape = FALSE;
            }
        }
        if ($current_piece !== ']') {
            throw new LexException("unexpectedly found end while in set");
        }
        $chars = array();
        while (($offset = array_search(NULL, $tokens, TRUE)) !== FALSE) {
            if ($offset === 0 || $offset === count($tokens) - 1) {
                $tokens[$offset] = "-";
            } else {
                $prev_token = $tokens[$offset - 1];
                $next_token = $tokens[$offset + 1];
                if (is_null($prev_token))
                    $prev_token = "-";
                if (is_null($next_token))
                    $next_token = "-";
                if (is_array($prev_token) || is_array($next_token)) {
                    $tokens[$offset] = "-";
                } else {
                    unset($tokens[$offset - 1]);
                    unset($tokens[$offset]);
                    unset($tokens[$offset + 1]);
                    foreach (range($prev_token, $next_token) as $range_token) {
                        $chars[] = (string) $range_token;
                    }
                    $tokens = array_values($tokens);
                }
            }
        }
        foreach ($tokens as $token) {
            if (is_string($token)) {
                $chars[] = $token;
            } else 
                if (is_array($token)) {
                    $chars = array_merge($chars, $token);
                } else {
                    throw new \RuntimeException("shouldn't be reached");
                }
        }
        return new Set($chars, ! $is_negative);
    }

    private function _lex_set_getEscaped($char)
    {
        switch ($char) {
            case 't':
                return "\t";
            case 'r':
                return "\r";
            case 'n':
                return "\n";
            case 'v':
                return "\v";
            case 'x':
                return $this->_lex_hex();
        }
        $groups = Set::getGroups() + Set::getInverseGroups();
        if (array_key_exists($char, $groups)) {
            return $groups[$char];
        }
        return $char;
    }

    private function _lex_repeat()
    {
        $start_offset = $this->_current_offset;
        $numbers = array(
            "0",
            "1",
            "2",
            "3",
            "4",
            "5",
            "6",
            "7",
            "8",
            "9"
        );
        $first_number = NULL;
        $second_number = NULL;
        if ($this->_current_offset >= count($this->_regex_pieces)) {
            $this->_current_offset = $start_offset;
            return NULL;
        }
        while (in_array($this->_regex_pieces[$this->_current_offset], $numbers, TRUE)) {
            $first_number .= $this->_regex_pieces[$this->_current_offset];
            $this->_current_offset ++;
        }
        if (is_null($first_number)) {
            $this->_current_offset = $start_offset;
            return NULL;
        }
        if (! in_array($this->_regex_pieces[$this->_current_offset], array(
            ",",
            "}"
        ), TRUE)) {
            $this->_current_offset = $start_offset;
            return NULL;
        }
        if ($this->_regex_pieces[$this->_current_offset] === ",") {
            $this->_current_offset ++;
            while (in_array($this->_regex_pieces[$this->_current_offset], $numbers, TRUE)) {
                $second_number .= $this->_regex_pieces[$this->_current_offset];
                $this->_current_offset ++;
            }
            if ($this->_regex_pieces[$this->_current_offset] !== "}") {
                $this->_current_offset = $start_offset;
                return NULL;
            }
        } else {
            $second_number = $first_number;
        }
        $this->_current_offset ++;
        settype($first_number, "int");
        if (! is_null($second_number)) {
            settype($second_number, "int");
            if ($first_number > $second_number) {
                throw new LexException("repeat found with min higher than max");
            }
        }
        
        return array(
            $first_number,
            $second_number
        );
    }

    static private function _isReadyForRepeat(array $tokens)
    {
        if (! $tokens) {
            throw new LexException("unexpected repeat");
        }
        $last_token = end($tokens);
        if ($last_token instanceof Repeat) {
            throw new LexException("unexpected repeat");
        }
        if ($last_token instanceof Special) {
            throw new LexException("unexpected repeat");
        }
        if ($last_token === '|') {
            throw new LexException('unexpected repeat');
        }
    }
}

?>
