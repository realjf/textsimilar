class libCalcTextSimilar
{
    private static $str1 = "";
    private static $str2 = "";
    private static $c = [];

    // 韵母表
    private static $vowels = [
        'a' => 1,
        'o' => 2,
        'e' => 3,
        'i' => 4,
        'u' => 5,
        'v' => 6,
        'ai' => 7,
        'ei' => 7,
        'ui' => 8,
        'ao' => 9,
        'ou' => 'A',
        'iu' => 'B',
        'ie' => 'C',
        've' => 'D',
        'er' => 'E',
        'an' => 'F',
        'en' => 'G',
        'in' => 'H',
        'un' => 'I',
        'ven' => 'J',
        'ang' => 'F',
        'eng' => 'G',
        'ing' => 'H',
        'ong' => 'K'
    ];

    // 声母表
    private static $consonants = [
        'b' => 1,
        'p' => 2,
        'm' => 3,
        'f' => 4,
        'd' => 5,
        't' => 6,
        'n' => 7,
        'l' => 7,
        'g' => 8,
        'k' => 9,
        'h' => 'A',
        'j' => 'B',
        'q' => 'C',
        'x' => 'D',
        'zh' => 'E',
        'ch' => 'F',
        'sh' => 'G',
        'r' => 'H',
        'z' => 'E',
        'c' => 'F',
        's' => 'G',
        'y' => 'I',
        'w' => 'J',
    ];

    /**
     * 字符音形编码
     */
    private static $wordQueue1 = "";
    private static $wordQueue2 = "";

    // 需要过滤的标点符号
    private static $stopWords = [
        ",",
        ".",
        "。",
        "，",
        "...",
        "……",
        "~",
        "'",
        "!",
        "！",
        "?",
        "？",
        "：",
        ":",
        "——",
        '"',
        "”",
        "“",
        "@",
        "+",
        "-",
        "_",
        "(",
        ")",
        "（",
        "）",
        "|",
        "#",
        "|",
        "%",
        "$",
        "￥",
        "*",
        "=",
        "&",
        "《",
        "》",
        "[",
        "]",
        "{",
        "}",
        "【",
        "】",
        "<",
        ">",
        ";",
        "；",
        "`",
        "^",
        "/",
        "\\",
    ];

    /**
     * 获取音形编码
     * @param $string
     * @return string
     */
    public static function getWordQueue($string)
    {
        // 过滤标点符号
        $stopWords = @file_get_contents(__DIR__ . "/stop_word_table.txt");
        if($stopWords){
            $stopWords = explode("\n", $stopWords);
            $stopWords = array_map(function ($v){return trim($v);}, $stopWords);
            $stopWords = array_values(array_flip(array_flip($stopWords)));
            self::$stopWords = $stopWords;
        }
        // 保存到redis
        $rediskey = "msg_auto_ban_stop_word_table";
        mdlBase::instance()->ypRedis()->set($rediskey, json_encode(self::$stopWords, JSON_UNESCAPED_UNICODE));
//        __e((self::$stopWords));
        $string = str_replace(self::$stopWords, "", $string);
        $string = preg_split('//u', $string, null, PREG_SPLIT_NO_EMPTY);
        $queue = "";
        foreach ($string as $v){
            $queue .= self::calcWordQueue($v);
        }
        return $queue;
    }

    /**
     * 计算字符编码
     * @param $string
     * @return string
     */
    public static function calcWordQueue($string)
    {
        $queue = "";
        if($string){
            $pinyin = \clsPinyin::convert2Pinyin($string);
            $string_vowel = ""; // 字符韵母
            $string_consonant = ""; // 字符声母
            foreach (self::$consonants as $consonant => $code){
                $pos = strpos($pinyin, $consonant);
                if($pos !== false && $pos >= 0){ // 找到声母
                    $string_consonant = strlen($string_consonant) >= strlen($consonant) ? $string_consonant : $consonant;
                }
            }
            $pinyin = substr($pinyin, strlen($string_consonant)); // 去掉找到的声母
            foreach (self::$vowels as $vowel => $code){
                $pos = strpos($pinyin, $vowel);
                if($pos !== false && $pos >= 0){ // 找到匹配的韵母
                    $string_vowel = strlen($string_vowel) >= strlen($vowel) ? $string_vowel : $vowel;
                }
            }
            $queue = self::$consonants[$string_consonant] . self::$vowels[$string_vowel]; // 字符编码
        }
        return $queue;
    }

    /**
     * 获取均衡相似度
     */
    public static function getBalanceSimilar($string1, $string2)
    {
        $similar1 = self::getSimilar($string1, $string2);
        $similar2 = self::calcWordQueueSimilar($string1, $string2);
        $similar = $similar1 == 1 || $similar2 == 1 ? 1 : ($similar1 * 1/2 + $similar2 * 1/2); // 音形和字形各占50%
        return round($similar, 4);
    }

    /**
     * 获取音形编码相似度
     * @return float|int
     */
    public static function calcWordQueueSimilar($string1, $string2)
    {
        self::$wordQueue1 = self::getWordQueue($string1);
        self::$wordQueue2 = self::getWordQueue($string2);
        $len1 = \clsTools::zlen(self::$wordQueue1, 3);
        $len2 = \clsTools::zlen(self::$wordQueue2, 3);
        $len = \clsTools::zlen(self::getLCS(self::$wordQueue1, self::$wordQueue2, $len1, $len2), 3);
        return $len * 2 / ($len1 + $len2);
    }

    /**
     * 返回串一和串二的最长公共子序列
     * @param $str1
     * @param $str2
     * @param int $len1
     * @param int $len2
     * @return string
     */
    public static function getLCS($str1, $str2, $len1 = 0, $len2 = 0) {
        self::$str1 = $str1;
        self::$str2 = $str2;
        if ($len1 == 0) $len1 = \clsTools::zlen($str1, 3);
        if ($len2 == 0) $len2 = \clsTools::zlen($str2, 3);
        self::initC($len1, $len2);
        return self::printLCS($len1 - 1, $len2 - 1);
    }

    /**
     * 返回两个串的相似度
     * @param $str1
     * @param $str2
     * @return float|int
     */
    public static function getSimilar($str1, $str2) {
        $len1 = \clsTools::zlen($str1, 3);
        $len2 = \clsTools::zlen($str2, 3);
        $len = \clsTools::zlen(self::getLCS($str1, $str2, $len1, $len2), 3);
        return $len * 2 / ($len1 + $len2);
    }

    /**
     * @param $len1
     * @param $len2
     */
    public static function initC($len1, $len2) {
        for ($i = 0; $i < $len1; $i++) self::$c[$i][0] = 0;
        for ($j = 0; $j < $len2; $j++) self::$c[0][$j] = 0;
        for ($i = 1; $i < $len1; $i++) { // 第一个字符串
            for ($j = 1; $j < $len2; $j++) { // 第二个字符串
                if (self::$str1[$i] == self::$str2[$j]) { // 字符相同
                    self::$c[$i][$j] = self::$c[$i - 1][$j - 1] + 1;
                } else if (self::$c[$i - 1][$j] >= self::$c[$i][$j - 1]) {
                    self::$c[$i][$j] = self::$c[$i - 1][$j];
                } else {
                    self::$c[$i][$j] = self::$c[$i][$j - 1];
                }
            }
        }
    }

    /**
     * @param $i
     * @param $j
     * @return string
     */
    public static function printLCS($i, $j) {
        if ($i == 0 || $j == 0) {
            if (self::$str1[$i] == self::$str2[$j]) return self::$str2[$j];
            else return "";
        }
        if (self::$str1[$i] == self::$str2[$j]) {
            return self::printLCS($i - 1, $j - 1).self::$str2[$j];
        } else if (self::$c[$i - 1][$j] >= self::$c[$i][$j - 1]) {
            return self::printLCS($i - 1, $j);
        } else {
            return self::printLCS($i, $j - 1);
        }
    }
}
