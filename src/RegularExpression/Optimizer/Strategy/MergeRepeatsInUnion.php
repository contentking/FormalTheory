<?php
namespace FormalTheory\RegularExpression\Optimizer\Strategy;

use FormalTheory\RegularExpression\Optimizer\Strategy;
use FormalTheory\RegularExpression\Token\Repeat;
use FormalTheory\RegularExpression\Token\Union;
use FormalTheory\RegularExpression\Token;

/*
 * Examples:
 * (1|1{2}) -> 1{1,2}
 * (1|11|1{3,})? -> 1*
 */
class MergeRepeatsInUnion extends Strategy
{

    const COST = 0;

    const SUCCESS = .5;

    function qualifiedClassNames()
    {
        return array(
            Union::class
        );
    }

    function qualifier(Token $token)
    {
        $sub_tokens = $token->getTokens();
        return count($sub_tokens) > 1;
    }

    function run(Token $token)
    {
        $sub_tokens = $token->getTokens();
        $sub_token_data_array = array_map(function ($sub_token) {
            return $sub_token instanceof Repeat ? array(
                $sub_token->getToken(),
                $sub_token->isGreedy(),
                $sub_token->getMinNumber(),
                $sub_token->getMaxNumber(),
                $sub_token
            ) : array(
                $sub_token,
                TRUE,
                1,
                1,
                $sub_token
            );
        }, $sub_tokens);
        $sub_token_data_array_count = count($sub_token_data_array);
        foreach ($sub_token_data_array as $i => $sub_token_data1) {
            for ($j = $i + 1; $j < $sub_token_data_array_count; $j ++) {
                $sub_token_data2 = $sub_token_data_array[$j];

                if ($sub_token_data1[1] !== $sub_token_data2[1]) {
                    continue;
                }

                if ((is_null($sub_token_data1[3]) || $sub_token_data1[3] >= $sub_token_data2[2] || is_null($sub_token_data2[3]) || $sub_token_data2[3] >= $sub_token_data1[2]) && $sub_token_data1[0]->compare($sub_token_data2[0])) {
                    unset($sub_token_data_array[$i]);
                    unset($sub_token_data_array[$j]);
                    $new_sub_tokens = array();
                    foreach ($sub_token_data_array as $sub_token_data) {
                        $new_sub_tokens[] = $sub_token_data[4];
                    }
                    $new_sub_tokens[] = new Repeat($sub_token_data1[0], $sub_token_data1[1], min($sub_token_data1[2], $sub_token_data2[2]), is_null($sub_token_data1[3]) || is_null($sub_token_data2[3]) ? NULL : max($sub_token_data1[3], $sub_token_data2[3]));
                    return new Union($new_sub_tokens);
                }
            }
        }
        return FALSE;
    }
}

?>