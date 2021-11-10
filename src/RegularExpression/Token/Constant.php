<?php
namespace FormalTheory\RegularExpression\Token;

use FormalTheory\RegularExpression\Expression;
use FormalTheory\RegularExpression\Token;

class Constant extends Token
{

    private $_string;
    public static $encode_nonprintable_chars = true;

    static function escapeChar($char)
    {
        if (! is_string($char) || strlen($char) !== 1) {
            throw new \RuntimeException("bad string variable");
        }

        if (self::$encode_nonprintable_chars) {
            switch ($char) {
                case "\n":
                    return '\n';
                case "\t":
                    return '\t';
                case "\r":
                    return '\r';
                case "\v":
                    return '\v';
            }
        }

        switch ($char) {
            case "^":
            case "$":
            case "*":
            case "+":
            case "?":
            case ".":
            case "|":
            case "\\":
            case "(":
            case ")":
            case "[":
            case "]":
            case "{": /* case "{" */
				return "\\{$char}";
        }
        if (self::$encode_nonprintable_chars) {
            if (ctype_print($char)) {
                return $char;
            }
            $hex = dechex(ord($char));
            if (strlen($hex) === 1)
                $hex = "0{$hex}";
            return "\\x{$hex}";
        } else {
            return $char;
        }
    }

    function __construct($string)
    {
        if (! is_string($string) || strlen($string) !== 1) {
            throw new \RuntimeException("bad string variable");
        }
        $this->_string = $string;
    }

    function getString()
    {
        return $this->_string;
    }

    function _toString()
    {
        return self::escapeChar($this->_string);
    }

    function getMatches()
    {
        return array(
            Expression::createFromString($this->_string)
        );
    }

    function getFiniteAutomataClosure()
    {
        $string = $this->_string;
        return function ($fa, $start_states, $end_states) use($string) {
            $start_states[1]->addTransition($string, $end_states[2]);
            $start_states[2]->addTransition($string, $end_states[2]);
        };
    }

    protected function _compare($token)
    {
        return $this->_string === $token->_string;
    }

    public function getMinLength(): int
    {
        return 1;
    }

    public function getMaxLength(): int
    {
        return 1;
    }
}
