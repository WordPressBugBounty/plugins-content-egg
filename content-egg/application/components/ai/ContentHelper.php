<?php

namespace ContentEgg\application\components\ai;

defined('\ABSPATH') || exit;

use  ContentEgg\application\helpers\TextHelper;
use ContentEgg\application\vendor\parsedown\Parsedown;

use function ContentEgg\prn;
use function ContentEgg\prnx;

/**
 * ContentHelper class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2024 keywordrush.com
 */

class ContentHelper
{
    public static function listToArray($text, $max_length = 300)
    {
        if (!strstr($text, "\n") && substr_count($text, ',') >= 2)
            $lines = explode(",", $text);
        else
            $lines = preg_split("~\n~", $text, -1, PREG_SPLIT_NO_EMPTY);

        $lines = self::tryFixList($lines);
        if (!self::isList($lines))
            return array();

        $res = array();

        foreach ($lines as $i => $line)
        {
            if (preg_match('~:$~', $line))
                continue;

            if ($i == count($lines) - 1 && count($lines) > 3)
            {
                if (!preg_match('~^\d~', $line) && preg_match('~^\d~', $lines[$i - 1]))
                    continue;
            }

            $line = trim($line);
            $line = preg_replace('~^\d+\.\s~', '', $line);
            $line = preg_replace('~^\d+\)\s~', '', $line);
            $line = trim($line, " \t\r\n\"'.-");
            $line = strip_tags($line);
            $line = \sanitize_text_field($line);

            if (!$line || mb_strlen($line, 'UTF-8') > $max_length)
                continue;

            $res[] = $line;
        }

        return $res;
    }

    public static function isList(array $lines)
    {
        $list_items = array();
        foreach ($lines as $line)
        {
            $line = trim($line, " \t\r\n\"'");

            if (!preg_match('~^\d+\.\s~', $line) && !preg_match('~^-~', $line) && !preg_match('~^\d+\)\s~', $line))
                continue;

            $list_items[] = $line;
        }

        if ($list_items)
            return true;
        else
            return false;
    }

    public static function tryFixList(array $lines)
    {
        if (self::isList($lines))
            return $lines;

        if (count($lines) < 3)
            return $lines;

        foreach ($lines as $i => $line)
        {
            $line = trim($line, " \t\r\n\"' ");

            if ($i == 0 && mb_strlen($line, 'UTF-8') > 90)
                continue;

            if (mb_strlen($line, 'UTF-8') > 180)
                continue;

            $lines[$i] = '- ' . $line;
        }

        return $lines;
    }

    public static function prepareTitle($text)
    {
        if (strstr($text, "\n"))
        {
            $list = ContentHelper::listToArray($text);
            $text = reset($list);
        }

        $text = \sanitize_text_field($text);
        $text = trim($text, " \".");

        return $text;
    }

    public static function prepareProductTitle($text)
    {
        return self::prepareTitle($text);
    }

    public static function preparePostTitle($text)
    {
        return TextHelper::truncate(self::prepareTitle($text), 200);
    }

    public static function prepareMarkdown($text)
    {
        $parsedown = new Parsedown();
        $text = $parsedown->text($text);
        $text = TextHelper::sanitizeHtml($text);
        $text = trim($text, " \"");
        return $text;
    }

    public static function prepareArticle($text, $title = '')
    {
        $text = str_replace('```html', '', $text);
        $text = str_replace('```', '', $text);

        if (strstr($text, '<h1>'))
            $text = self::headerDown($text);

        if ($title)
        {
            $text = str_replace('<h2>' . $title . '</h2>', '', $text);
            $text = str_replace('<h3>' . $title . '</h3>', '', $text);
        }

        $text = trim($text);
        $text = preg_replace('/<title>.+?<\/title>/ui', '', $text);
        $text = preg_replace('/<style>.+?<\/style>/ims', '', $text);
        $text = preg_replace('/<script>.+?<\/script>/ims', '', $text);
        $text = TextHelper::sanitizeHtml($text);
        return $text;
    }

    public static function headerDown($html)
    {
        for ($i = 1; $i <= 5; $i++)
        {
            $r = $i + 1;
            $html = str_replace('<h' . $i . '>', '<hhhhhh' . $r . '>', $html);
            $html = str_replace('</h' . $i . '>', '</hhhhhh' . $r . '>', $html);
        }

        $html = str_replace('hhhhhh', 'h', $html);
        return $html;
    }

    public static function htmlToText($html)
    {
        $text = preg_replace(
            array(
                '~</?((div)|(h[1-9])|(ins)|(br)|(p)|(pre))~iu',
                '~</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))~iu',
                '~</?((table)|(th)|(td)|(caption))~iu',
            ),
            array(
                "\n\$0", "\n\$0", "\n\$0",
            ),
            $html
        );

        $text = \wp_strip_all_tags($text);
        $text = preg_replace("~\r+~u", "\n", $text);
        $text = preg_replace("~\n+~u", "\n", $text);
        $text = trim($text);

        return $text;
    }

    public static function fixAiResponse($text)
    {
        $text = preg_replace('/^As an AI language model.+?However,/ims', '', $text);
        $text = preg_replace('/^As an AI language model.+?but generally,/ims', '', $text);
        $text = preg_replace('/^As an AI language model.+?It is recommended/ims', 'It is recommended', $text);
        $text = trim($text, " ,");
        return $text;
    }

    public static function isAiGenerated($text)
    {
        $footprints = array(
            ' AI language',
            ' AI model',
        );

        foreach ($footprints as $footprint)
        {
            if (mb_stripos($text, $footprint) !== false)
                return true;
        }

        return false;
    }
}
