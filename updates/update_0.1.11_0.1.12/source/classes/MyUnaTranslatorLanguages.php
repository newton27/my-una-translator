<?php defined('BX_DOL') or die('hack attempt');

/** UNA locale metadata is independent of model availability and existing jobs. */
final class MyUnaTranslatorLanguages
{
    private const ALIASES=['pt-br'=>'pt-br','zh-hans'=>'zh-cn','zh-hant'=>'zh-tw'];
    private const META=[
        'sq'=>['Shqip','al','sq_AL'], 'ar'=>['العربية','sa','ar_SA','RTL'],
        'az'=>['Azərbaycanca','az','az_AZ'], 'eu'=>['Euskara','es','eu_ES'],
        'bn'=>['বাংলা','bd','bn_BD'], 'bg'=>['Български','bg','bg_BG'],
        'ca'=>['Català','es','ca_ES'], 'zh-cn'=>['简体中文','cn','zh_CN'],
        'zh-tw'=>['繁體中文','tw','zh_TW'], 'cs'=>['Čeština','cz','cs_CZ'],
        'da'=>['Dansk','dk','da_DK'], 'nl'=>['Nederlands','nl','nl_NL'],
        'eo'=>['Esperanto','','eo'], 'et'=>['Eesti','ee','et_EE'],
        'fi'=>['Suomi','fi','fi_FI'], 'fr'=>['Français','fr','fr_FR'],
        'gl'=>['Galego','es','gl_ES'], 'de'=>['Deutsch','de','de_DE'],
        'el'=>['Ελληνικά','gr','el_GR'], 'he'=>['עברית','il','he_IL','RTL'],
        'hi'=>['हिन्दी','in','hi_IN'], 'hu'=>['Magyar','hu','hu_HU'],
        'id'=>['Bahasa Indonesia','id','id_ID'], 'ga'=>['Gaeilge','ie','ga_IE'],
        'it'=>['Italiano','it','it_IT'], 'ja'=>['日本語','jp','ja_JP'],
        'ko'=>['한국어','kr','ko_KR'], 'ky'=>['Кыргызча','kg','ky_KG'],
        'lv'=>['Latviešu','lv','lv_LV'], 'lt'=>['Lietuvių','lt','lt_LT'],
        'ms'=>['Bahasa Melayu','my','ms_MY'], 'nb'=>['Norsk bokmål','no','nb_NO'],
        'fa'=>['فارسی','ir','fa_IR','RTL'], 'pl'=>['Polski','pl','pl_PL'],
        'pt'=>['Português','pt','pt_PT'], 'pt-br'=>['Português (Brasil)','br','pt_BR'],
        'ro'=>['Română','ro','ro_RO'], 'ru'=>['Русский','ru','ru_RU'],
        'sk'=>['Slovenčina','sk','sk_SK'], 'sl'=>['Slovenščina','si','sl_SI'],
        'es'=>['Español','es','es_ES'], 'sw'=>['Kiswahili','tz','sw_TZ'],
        'sv'=>['Svenska','se','sv_SE'], 'tl'=>['Tagalog','ph','tl_PH'],
        'th'=>['ไทย','th','th_TH'], 'tr'=>['Türkçe','tr','tr_TR'],
        'uk'=>['Українська','ua','uk_UA'], 'ur'=>['اردو','pk','ur_PK','RTL'],
        'vi'=>['Tiếng Việt','vn','vi_VN'],
    ];
    public static function libreTarget($target)
    {
        if (!is_string($target) || !preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/D',$target) || strtolower($target)==='en')
            throw new RuntimeException('Unsupported LibreTranslate target language.');
        $code=strtolower($target);
        return ['pt-br'=>'pt-BR','zh-hans'=>'zh-Hans','zh-hant'=>'zh-Hant'][$code]??$code;
    }
    public static function preset($code,$name=null)
    {
        $code=strtolower($code);
        $code=self::ALIASES[$code]??$code;
        $target=['zh-cn'=>'zh-Hans','zh-tw'=>'zh-Hant'][$code]??self::libreTarget($code);
        $meta=self::META[$code]??[$name??$code,'',str_replace('-','_',$code)];
        return ['name'=>$name??$code,'native'=>$meta[0],'target'=>strtoupper($target),'flag'=>$meta[1],'country'=>$meta[2],'direction'=>$meta[3]??'LTR'];
    }
    public static function fromLibre(array $rows)
    {
        $routes=null;
        foreach($rows as $row) if (is_array($row) && ($row['code']??'')==='en') $routes=$row['targets']??null;
        if (!is_array($routes) || !array_is_list($routes)) throw new RuntimeException('LibreTranslate did not report English translation routes.');
        $result=[];
        foreach($rows as $row) {
            if (!is_array($row) || !is_string($row['code']??null) || !is_string($row['name']??null) || strlen($row['name'])>160 || !mb_check_encoding($row['name'],'UTF-8'))
                throw new RuntimeException('LibreTranslate returned an invalid language inventory.');
            if ($row['code']==='en' || !in_array($row['code'],$routes,true)) continue;
            $api=self::libreTarget($row['code']); $code=strtolower($api); $code=self::ALIASES[$code]??$code;
            $result[$code]=self::preset($code,$row['name']);
        }
        if (!$result) throw new RuntimeException('LibreTranslate has no installed translation routes from English.');
        uasort($result,fn($a,$b)=>strcasecmp($a['name'],$b['name']));
        return $result;
    }
}

