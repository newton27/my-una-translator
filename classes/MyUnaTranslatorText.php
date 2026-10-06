<?php defined('BX_DOL') or die('hack attempt');

/** Protect executable markup and UNA substitution tokens across translation. */
final class MyUnaTranslatorText
{
    private static function brands()
    {
        return '(?:My UNA (?:Module Doctor|Mail Doctor|World Tickets|World|Doctor|Finances|Jitsi|Translator)|MyUNAWorld|(?-i:UNA)(?:\.IO)?|Jitsi|OvenMediaEngine)';
    }

    private static function pattern()
    {
        $tagBody='(?:[^<>"\x27]|"[^"]*"|\x27[^\x27]*\x27)*';
        return '~<script\b'.$tagBody.'>.*?</script\s*>|<style\b'.$tagBody.'>.*?</style\s*>|<!--[\s\S]*?-->|<'.$tagBody.'>|https?://[^\s<>"\x27]+|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}|__[A-Z0-9_:.-]+__|\{[^{}\r\n]+\}|%%|%(?:\d+\$)?[-+0 \x27]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]|&(?:[A-Z][A-Z0-9]+|\#\d+|\#x[0-9a-f]+);|\b'.self::brands().'\b~isu';
    }

    /** Translate only text-bearing fragments. Protected bytes never leave PHP. */
    public static function encodeFragments($source,$localSeparators=false)
    {
        self::encode($source); // Shared UTF-8 and control-character validation.
        preg_match_all(self::pattern(),$source,$matches,PREG_OFFSET_CAPTURE);
        $parts=[]; $segments=[]; $offset=0;
        $append=function($text) use (&$parts,&$segments,$localSeparators) {
            if (!preg_match('/\p{L}/u',$text)) { $parts[]=$text; return; }
            if ($localSeparators && preg_match('/\A[\s,;:]+/u',$text,$prefix)) {
                $parts[]=$prefix[0]; $text=substr($text,strlen($prefix[0]));
            }
            preg_match('/^(\s*)([\s\S]*?\S)(\s*)$/u',$text,$space);
            $id='t'.count($segments);
            $parts[]=$space[1]; $parts[]=['segment'=>$id]; $parts[]=$space[3];
            $segments[$id]=$space[2];
        };
        foreach($matches[0] as $match) {
            $append(substr($source,$offset,$match[1]-$offset));
            $parts[]=$match[0]; $offset=$match[1]+strlen($match[0]);
        }
        $append(substr($source,$offset));
        $xml='<doc>'; $characters=0;
        foreach($segments as $id=>$text) {
            $xml.='<seg id="'.$id.'">'.htmlspecialchars($text,ENT_QUOTES|ENT_XML1,'UTF-8').'</seg>';
            $characters+=mb_strlen($text,'UTF-8');
        }
        return ['xml'=>$xml.'</doc>','segments'=>$segments,'parts'=>$parts,'source'=>$source,'characters'=>$characters];
    }

    public static function decodeFragments($xml,array $packet)
    {
        if (!is_string($xml) || strlen($xml)>1048576 || stripos($xml,'<!DOCTYPE')!==false || stripos($xml,'<!ENTITY')!==false)
            throw new RuntimeException('Translation returned unsafe XML.');
        $previous=libxml_use_internal_errors(true); $doc=new DOMDocument();
        $ok=$doc->loadXML($xml,LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        if (!$ok || !$doc->documentElement || $doc->documentElement->tagName!=='doc' || $doc->documentElement->attributes->length || $doc->childNodes->length!==1)
            throw new RuntimeException('Translation returned invalid XML.');
        $translated=[];
        foreach($doc->documentElement->childNodes as $node) {
            if ($node->nodeType===XML_TEXT_NODE && trim($node->nodeValue)==='') continue;
            if ($node->nodeType!==XML_ELEMENT_NODE || $node->tagName!=='seg' || $node->attributes->length!==1 || !$node->hasAttribute('id'))
                throw new RuntimeException('Translation changed a text fragment.');
            $id=$node->getAttribute('id');
            if (!array_key_exists($id,$packet['segments']) || array_key_exists($id,$translated))
                throw new RuntimeException('Translation altered or duplicated a text fragment.');
            foreach($node->childNodes as $child)
                if (!in_array($child->nodeType,[XML_TEXT_NODE,XML_CDATA_SECTION_NODE],true))
                    throw new RuntimeException('Translation added markup to a text fragment.');
            if (trim($node->textContent)==='') throw new RuntimeException('Translation returned an empty text fragment.');
            $translated[$id]=$node->textContent;
        }
        if (count($translated)!==count($packet['segments'])) throw new RuntimeException('Translation omitted a text fragment.');
        $result=''; $previousBrand=false;
        foreach($packet['parts'] as $part) {
            $piece=is_array($part)?$translated[$part['segment']]:$part;
            if ($piece==='') continue;
            $brand=!is_array($part) && preg_match('~\A'.self::brands().'\z~isu',$piece);
            // A translated possessive or hyphenated suffix may lose its punctuation.
            if (($previousBrand && preg_match('/\A[\p{L}\p{N}\p{M}_]/u',$piece)) ||
                ($brand && preg_match('/[\p{L}\p{N}\p{M}_]\z/u',$result))) $result.=' ';
            $result.=$piece; $previousBrand=(bool)$brand;
        }
        self::validate($packet['source'],$result);
        return $result;
    }

    public static function validate($source,$result)
    {
        $wanted=array_values(self::encode($source)['tokens']);
        $actual=array_values(self::encode($result)['tokens']);
        $ordered=function($tokens) { return array_values(array_filter($tokens,fn($s)=>strpos($s,'<')===0 || preg_match('/^%(?!\d+\$)/',$s))); };
        if ($ordered($wanted)!==$ordered($actual)) throw new RuntimeException('Translation reordered protected markup or format arguments.');
        sort($wanted); sort($actual);
        if ($wanted!==$actual) throw new RuntimeException('Translation introduced or omitted a placeholder, link, or markup.');
        $shape=function($text) {
            preg_match_all(self::pattern(),$text,$matches,PREG_OFFSET_CAPTURE);
            $shape=[]; $offset=0;
            foreach($matches[0] as $match) {
                if (strpos($match[0],'<')!==0) continue;
                $shape[]=[$match[0],trim(substr($text,$offset,$match[1]-$offset))!==''];
                $offset=$match[1]+strlen($match[0]);
            }
            if ($shape) $shape[]=['',trim(substr($text,$offset))!==''];
            return $shape;
        };
        if ($shape($source)!==$shape($result)) throw new RuntimeException('Translation moved text across protected markup.');
        if (trim($result)==='' && trim($source)!=='') throw new RuntimeException('Translation returned empty text.');
    }

    public static function encode($source)
    {
        if (!is_string($source) || !mb_check_encoding($source, 'UTF-8'))
            throw new RuntimeException('Source text must be valid UTF-8.');
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $source))
            throw new RuntimeException('Source text contains unsupported control characters.');
        if (preg_match_all(self::pattern(), $source, $matches, PREG_OFFSET_CAPTURE) === false)
            throw new RuntimeException('Source protection could not safely parse this text.');
        $tokens = []; $xml = '<doc>'; $offset = 0;
        $prefix = '__MUAT_' . substr(hash('sha256', $source), 0, 12) . '_';
        foreach ($matches[0] as $i => $match) {
            $xml .= htmlspecialchars(substr($source, $offset, $match[1] - $offset), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $marker = $prefix . $i . '__';
            $tokens[$marker] = $match[0];
            $xml .= '<keep>' . $marker . '</keep>';
            $offset = $match[1] + strlen($match[0]);
        }
        $xml .= htmlspecialchars(substr($source, $offset), ENT_QUOTES | ENT_XML1, 'UTF-8') . '</doc>';
        return ['xml' => $xml, 'tokens' => $tokens, 'source' => $source,
            'characters' => mb_strlen(strip_tags($xml), 'UTF-8')];
    }

    public static function decode($xml, array $packet)
    {
        if (!is_string($xml) || strlen($xml) > 1048576 || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false)
            throw new RuntimeException('Translation returned unsafe XML.');
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $ok = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        if (!$ok || !$doc->documentElement || $doc->documentElement->tagName !== 'doc' || $doc->documentElement->attributes->length)
            throw new RuntimeException('Translation returned invalid XML.');
        if ($doc->childNodes->length !== 1)
            throw new RuntimeException('Translation added unexpected XML content.');
        $result = ''; $seen = [];
        foreach ($doc->documentElement->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
                $result .= $node->nodeValue;
                continue;
            }
            if ($node->nodeType !== XML_ELEMENT_NODE || $node->nodeName !== 'keep' || $node->attributes->length)
                throw new RuntimeException('Translation changed protected markup.');
            foreach ($node->childNodes as $child)
                if (!in_array($child->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true))
                    throw new RuntimeException('Translation changed a protected token.');
            $marker = $node->textContent;
            if (!array_key_exists($marker, $packet['tokens']) || isset($seen[$marker]))
                throw new RuntimeException('Translation altered or duplicated a protected token.');
            $seen[$marker] = true;
            $result .= $packet['tokens'][$marker];
        }
        if (count($seen) !== count($packet['tokens']))
            throw new RuntimeException('Translation omitted a protected token.');
        $wantedMarkup = []; $actualMarkup = [];
        foreach ($packet['tokens'] as $marker => $value)
            if (strpos($value, '<') === 0 || preg_match('/^%(?!\d+\$)/', $value)) $wantedMarkup[] = $marker;
        foreach (array_keys($seen) as $marker)
            if (in_array($marker, $wantedMarkup, true)) $actualMarkup[] = $marker;
        if ($wantedMarkup !== $actualMarkup)
            throw new RuntimeException('Translation reordered markup or unnumbered format arguments.');
        self::validate($packet['source'],$result);
        return $result;
    }
}
