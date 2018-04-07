<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 为文章添加肉眼不可见的额外信息，可用于追踪未授权转载行为
 * 
 * @package HideText
 * @author journey.ad
 * @version 0.1
 * @link https://imjad.cn
 */
class HideText_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('HideText_Plugin', 'parse');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate() {}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
?>
        <h4>为文章添加肉眼不可见的额外信息，可用于追踪未授权转载行为</h4>
        在下方进行检测：<br>
        <script>
            const zeroPad = num => '00000000'.slice(String(num).length) + num;
            
            const textToBinary = text => (
              text.split('').map(char => zeroPad(char.charCodeAt(0).toString(2))).join(' ')
            );
            
            const binaryToZeroWidth = binary => (
              binary.split('').map((binaryNum) => {
                const num = parseInt(binaryNum, 10);
                if (num === 1) {
                  return '​'; // invisible &#8203;
                } else if (num === 0) {
                  return '‌'; // invisible &#8204;
                }
                return '‍'; // invisible &#8205;
              }).join('﻿') // invisible &#65279;
            );
            
            const zeroWidthToBinary = string => (
              string.split('﻿').map((char) => { // invisible &#65279;
                if (char === '​') { // invisible &#8203;
                  return '1';
                } else if (char === '‌') { // invisible &#8204;
                  return '0';
                }
                return ' '; // split up binary with spaces;
              }).join('')
            );
            
            const binaryToText = string => (
              string.split(' ').map(num => String.fromCharCode(parseInt(num, 2))).join('')
            );
            
            function encode(text){
              const binaryText = textToBinary(text);
              const zeroWidthText = binaryToZeroWidth(binaryText);
              return zeroWidthText;
            };
            
            function decode(zeroWidthText){
              zeroWidthText = zeroWidthText.replace(/[^​‌‍﻿]/g, '');
              const binaryText = zeroWidthToBinary(zeroWidthText);
              const textText = binaryToText(binaryText);
              return textText;
            };
            
            function test(){
                var text = document.getElementById('test').value;
                var resultel = document.getElementById('result');
                resultel.innerText = '';
                var result = decode(text);
                if(result.length > 2){
                    resultel.innerText = "发现隐藏文本：" + result;
                }else{
                    resultel.innerText = "什么都没有";
                }
            }
        </script>
        <textarea id="test" oninput="test()" autoComplete="off" style="width: 100%;height: 100px;"></textarea><br>
        <span id="result"></span>
<?php
        $auto = new Typecho_Widget_Helper_Form_Element_Radio('auto',
            array(
                '1' => '是',
                '0' => '否',
            ),'1', _t('自动插入'), _t('启用后将自动向文章随机位置插入内容信息，也可在写文章时通过填写&lt;!--ap--&gt;手动选择插入位置'));
        $form->addInput($auto);

        $text = new Typecho_Widget_Helper_Form_Element_Textarea('text', null, '本内容原标题为「{title}」，由{author}于{time}创作，原文地址：{permalink}', _t('自定义插入内容'), _t('以下是一些模板字符串<br>{siteTitle} 网站名<br>{title} 文章标题<br>{author} 文章作者<br>{permalink} 文章链接<br>{time} 文章发布日期<br>'));
        $form->addInput($text);
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}
    
    public static function parse($content,$widget,$last)
    {
        $config = Typecho_Widget::widget('Widget_Options')->plugin('HideText');
        $search  = array(
            '{siteTitle}',
            '{title}',
            '{author}',
            '{permalink}',
            '{time}'
        );
        $replace = array(
            Helper::options()->title,
            $widget->title,
            $widget->author->name,
            $widget->permalink,
            date('Y-m-d', $widget->created)
        );
        $text = str_replace($search, $replace, $config->text);
        $text = self::encode($text);

        $auto = $config->auto;
        if($auto == 1){
            $p = self::strpos_all($content, '<p>');
            $pos = $p[array_rand($p)];
            $content = substr_replace($content, $text, $pos, 0);
        }

        $content = str_replace('<!--ap-->', $text, $content);
        
        return $content;
    }
    
    private static function encode($text){
        $binaryText = self::textToBinary($text);
        $zeroWidthText = self::binaryToZeroWidth($binaryText);
        return $zeroWidthText;
    }
    
    private static function decode($zeroWidthText){
        $zeroWidthText = preg_replace('/[^​‌‍﻿]/', '', $zeroWidthText);
        $binaryText = self::zeroWidthToBinary($zeroWidthText);
        $textText = self::binaryToText($binaryText);
        return $textText;
    }

    private static function strpos_all($haystack, $needle) {
        $offset = 0;
        $allpos = array();
        while (($pos = strpos($haystack, $needle, $offset)) !== false) {
            $offset   = $pos + 1;
            $allpos[] = $pos;
        }
        return $allpos;
    }
    
    private static function getUTF16CodeUnits($string){
        $string = substr(json_encode($string), 1, -1);
        preg_match_all("/\\\\u[0-9a-fA-F]{4}|./mi", $string, $matches);
        return $matches[0];
    }
    
    private static function JS_charCodeAt($string, $index=0){
        $utf16CodeUnits = self::getUTF16CodeUnits($string);
        $unit = $utf16CodeUnits[$index];
        if(strlen($unit) > 1){
            $hex = substr($unit, 2);
            return hexdec($hex);
        }
        else {
            return ord($unit);
        }
    }
    
    private static function uchr($codes){
        if (is_scalar($codes)) $codes= func_get_args();
        $str= '';
        foreach ($codes as $code) $str.= html_entity_decode('&#'.$code.';',ENT_NOQUOTES,'UTF-8');
        return $str;
    }
    
    private static function textToBinary($Text){
        $chars = preg_split('//u', $Text, null, PREG_SPLIT_NO_EMPTY);
        $result = '';
        foreach($chars as $char){
            $result .= str_pad(decbin(self::JS_charCodeAt($char)), 8, 0, STR_PAD_LEFT).' ';
        }
        return rtrim($result);
    }
    
    private static function binaryToText($string){
        $strings = explode(' ', $string);
        $result = array();
        foreach($strings as $k => $num){
            if($num !== ''){
                $binaryNum = intval($num, 2);
                array_push($result, uchr($binaryNum));
            }else{
                array_push($result, ' ');
            }
        }
        return implode($result);
    }
    
    private static function binaryToZeroWidth($binary){
        $binarys = str_split($binary);
        $result = array();
        foreach($binarys as $k => $binaryNum){
            if(!ctype_space($binaryNum)){
                $num = intval($binaryNum, 10);
                if ($num === 1){
                    array_push($result, '​'); // invisible &#8203;
                } else if ($num === 0){
                    array_push($result, '‌'); // invisible &#8203;
                }
            }else{
                array_push($result, '‍'); // invisible &#8203;
            }
        }
        return implode('﻿', $result); // invisible &#65279;
    }
    
    private static function zeroWidthToBinary($string){
        $strings = explode('﻿', $string);
        $result = array();
        foreach($strings as $char){
            if ($char === '​'){ // invisible &#8203;
                array_push($result, '1');
            } else if ($char === '‌'){ // invisible &#8204;
                array_push($result, '0');
            }else{
                array_push($result, ' '); // split up binary with spaces;
            }
        }
        return implode($result);
    }
}
