<?php
// version 2.0.7, update 22-06-2025
// version 1.6.0, update 26-04-2025
// version 1.3.0, update 21-04-2025
// version 1.2.0, update 18-04-2025
namespace App\Services;

class ArrayServiceCommon
{
    function array_value($arr, $key, $default = "")
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    function array_filter_empty($arr)
    {
        if (empty($arr)) {
            return;
        }
        foreach ($arr as $k => $v) {
            if (empty($v)) {
                unset($arr[$k]);
            }
        }
        return $arr;
    }

    function array_addslashes(&$var)
    {
        if (is_array($var)) {
            foreach ($var as $k => &$v) {
                $this->array_addslashes($v);
            }
        } else {
            $var = addslashes($var);
        }
        return $var;
    }

    function array_stripslashes(&$var)
    {
        if (is_array($var)) {
            foreach ($var as $k => &$v) {
                $this->array_stripslashes($v);
            }
        } else {
            $var = stripslashes($var);
        }
        return $var;
    }

    function array_htmlspecialchars(&$var)
    {
        if (is_array($var)) {
            foreach ($var as $k => &$v) {
                $this->array_htmlspecialchars($v);
            }
        } else {
            $var = str_replace(
                ["&", '"', "<", ">"],
                ["&amp;", "&quot;", "&lt;", "&gt;"],
                $var
            );
        }
        return $var;
    }

    function array_trim(&$var)
    {
        if (is_array($var)) {
            foreach ($var as $k => &$v) {
                $this->array_trim($v);
            }
        } else {
            $var = trim($var);
        }
        return $var;
    }

    function array_diff_value($arr1, $arr2)
    {
        foreach ($arr1 as $k => $v) {
            if (isset($arr2[$k]) && $arr2[$k] == $v) {
                unset($arr1[$k]);
            }
        }
        return $arr1;
    }

    function arrlist_multisort($arrlist, $col, $asc = true)
    {
        $colarr = [];
        foreach ($arrlist as $k => $arr) {
            $colarr[$k] = $arr[$col];
        }
        $asc = $asc ? SORT_ASC : SORT_DESC;
        array_multisort($colarr, $asc, $arrlist);
        return $arrlist;
    }

    function arrlist_search(
        $arrlist,
        $cond = [],
        $orderby = [],
        $page = 1,
        $pagesize = 10000000
    ) {
        $resultarr = [];
        if (empty($arrlist)) {
            return $arrlist;
        }
        if ($cond) {
            foreach ($arrlist as $key => $val) {
                $ok = true;
                foreach ($cond as $k => $v) {
                    if (!isset($val[$k])) {
                        $ok = false;
                        break;
                    }
                    if (!is_array($v)) {
                        if ($val[$k] != $v) {
                            $ok = false;
                            break;
                        }
                    } else {
                        foreach ($v as $k3 => $v3) {
                            if ($k3 == "==" || empty($k3) || is_numeric($k3)) {
                                $ok = false;
                                if ($val[$k] == $v3) {
                                    $ok = true;
                                    break 2;
                                }
                            } elseif (
                                ($k3 == ">" && $val[$k] <= $v3) ||
                                ($k3 == "<" && $val[$k] >= $v3) ||
                                ($k3 == ">=" && $val[$k] < $v3) ||
                                ($k3 == "<=" && $val[$k] > $v3) ||
                                ($k3 == "==" && $val[$k] != $v3) ||
                                ($k3 == "!=" && $val[$k] == $v3) ||
                                ($k3 == "LIKE" && stripos($val[$k], $v3) === false)
                            ) {
                                $ok = false;
                                break 2;
                            }
                        }
                    }
                }
                if ($ok) {
                    $resultarr[$key] = $val;
                }
            }
        } else {
            $resultarr = $arrlist;
        }
        if ($orderby) {
            $k = key($orderby);
            $v = current($orderby);
            $resultarr = $this->arrlist_multisort($resultarr, $k, $v == 1);
        }
        $start = ($page - 1) * $pagesize;
        $resultarr = $this->array_assoc_slice($resultarr, $start, $pagesize);
        return $resultarr;
    }

    function array_assoc_slice($arrlist, $start, $length = 0)
    {
        if (isset($arrlist[0])) {
            return array_slice($arrlist, $start, $length);
        }
        $keys = array_keys($arrlist);
        $keys2 = array_slice($keys, $start, $length);
        $retlist = [];
        foreach ($keys2 as $key) {
            $retlist[$key] = $arrlist[$key];
        }
        return $retlist;
    }

    function arrlist_key_values($arrlist, $key, $value = null, $pre = "")
    {
        $return = [];
        if ($key) {
            foreach ((array) $arrlist as $k => $arr) {
                $return[$pre . $arr[$key]] = $value ? $arr[$value] : $k;
            }
        } else {
            foreach ((array) $arrlist as $arr) {
                $return[] = $arr[$value];
            }
        }
        return $return;
    }

    function arrlist_values($arrlist, $key)
    {
        if (!$arrlist) {
            return [];
        }
        $return = [];
        foreach ($arrlist as &$arr) {
            $return[] = $arr[$key];
        }
        return $return;
    }

    function arrlist_sum($arrlist, $key)
    {
        if (!$arrlist) {
            return 0;
        }
        $n = 0;
        foreach ($arrlist as &$arr) {
            $n += $arr[$key];
        }
        return $n;
    }

    function arrlist_max($arrlist, $key)
    {
        if (!$arrlist) {
            return 0;
        }
        $first = array_pop($arrlist);
        $max = $first[$key];
        foreach ($arrlist as &$arr) {
            if ($arr[$key] > $max) {
                $max = $arr[$key];
            }
        }
        return $max;
    }

    function arrlist_min($arrlist, $key)
    {
        if (!$arrlist) {
            return 0;
        }
        $first = array_pop($arrlist);
        $min = $first[$key];
        foreach ($arrlist as &$arr) {
            if ($min > $arr[$key]) {
                $min = $arr[$key];
            }
        }
        return $min;
    }

    function arrlist_change_key($arrlist, $key = "", $pre = "")
    {
        $return = [];
        if (empty($arrlist)) {
            return $return;
        }
        foreach ($arrlist as &$arr) {
            if (empty($key)) {
                $return[] = $arr;
            } else {
                $return[$pre . "" . $arr[$key]] = $arr;
            }
        }
        return $return;
    }

    function arrlist_keep_keys($arrlist, $keys = [])
    {
        !is_array($keys) and ($keys = [$keys]);
        foreach ($arrlist as &$v) {
            $arr = [];
            foreach ($keys as $key) {
                $arr[$key] = isset($v[$key]) ? $v[$key] : null;
            }
            $v = $arr;
        }
        return $arrlist;
    }

    function arrlist_chunk($arrlist, $key)
    {
        $r = [];
        if (empty($arrlist)) {
            return $r;
        }
        foreach ($arrlist as &$arr) {
            !isset($r[$arr[$key]]) and ($r[$arr[$key]] = []);
            $r[$arr[$key]][] = $arr;
        }
        return $r;
    }

    function arrlist_sort($array, $condition = array())
    {
        usort($array, function ($a, $b) use ($condition)
        {
            foreach ($condition as $field => $order)
            {
                if (!isset($a[$field]) || !isset($b[$field]))
                {
                    return isset($a[$field]) ? 1 : -1;
                }
                $comparison = (is_numeric($a[$field]) && is_numeric($b[$field]))
                ? $a[$field] - $b[$field]
                : strcmp((string) $a[$field], (string) $b[$field]);
                if ($comparison !== 0)
                {
                    return $order === 1 ? $comparison : -$comparison;
                }
            }
            return 0;
        });
        return $array;
    }

    function attend_array_text($attends)
    {
        $rt['present'] = '';
        $rt['late']    = '';
        $rt['absent']  = '';
        $rt['clear']   = '';
        $_attend       = array();
        foreach ($attends as $ymd => $attend)
        {
            $timestrape = strtotime($ymd);
            $m          = date('n', $timestrape);
            $d          = date('j', $timestrape);
            if (!isset($_attend[$attend]))
            {
                $_attend[$attend] = array();
            }
            if (isset($_attend[$attend][$m]))
            {
                $_attend[$attend][$m] .= $d.', ';
            }
            else
            {
                $_attend[$attend][$m] = $d.', ';
            }
        }
        foreach ($_attend as $attend => $ymd)
        {
            $a = '';
            foreach ($ymd as $m => $d)
            {
                $a .= $m.'月: '.substr($d, 0, -2).'&nbsp;&nbsp;&nbsp;&nbsp;';
            }
            $rt[$attend] = $a;
        }
        return $rt;
    }

    public function attend_text($str)
    {
        switch ($str)
        {
            case 'late':
                echo '請假';
                break;
            case 'absent':
                echo '取消';
                break;
            case 'clear':
                echo '清除';
                break;
            default:
                echo '出席';
                break;
        }
    }

    function arrlist_search_one($arrlist, $where = array(), $sort = array())
    {
        $arr = $this->arrlist_search($arrlist, $where, $sort);
        if (empty($arr))
        {
            return array();
        }
        return reset($arr);
    }
}
