<?php
/** @noinspection DuplicatedCode */

class ZipFolder
{
    protected $zip;
    protected $root;

    public function __construct()
    {
        $this->zip = new ZipArchive;
    }

    /**
     * 解压zip文件到指定文件夹
     *
     * @access public
     * @param string $zipfile 压缩文件路径
     * @param string $path 压缩包解压到的目标路径
     * @return bool 解压成功返回 true 否则返回 false
     */
    public function unzip($zipfile, $path)
    {
        if ($this->zip->open($zipfile) === true) {
            $file_tmp = @fopen($zipfile, "rb");
            $bin = fread($file_tmp, 15); //只读15字节 各个不同文件类型，头信息不一样。
            fclose($file_tmp);
            /* 只针对zip的压缩包进行处理 */
            if (true === $this->getTypeList($bin)) {
                $result = $this->zip->extractTo($path);
                $this->zip->close();
                return $result;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * 读取压缩包文件与目录列表
     *
     * @access public
     * @param string $zipfile 压缩包文件
     * @return array 文件与目录列表
     */
    public function fileList($zipfile)
    {
        $file_dir_list = array();
        $file_list = array();
        if ($this->zip->open($zipfile) == true) {
            for ($i = 0; $i < $this->zip->numFiles; $i++) {
                $numfiles = $this->zip->getNameIndex($i);
                if (preg_match('/\/$/i', $numfiles)) {
                    $file_dir_list[] = $numfiles;
                } else {
                    $file_list[] = $numfiles;
                }
            }
        }
        return array('files' => $file_list, 'dirs' => $file_dir_list);
    }

    /**
     * 得到文件头与文件类型映射表
     *
     * @param $bin string 文件的二进制前一段字符
     * @return boolean
     * @author wengxianhu
     * @date 2013-08-10
     */
    private function getTypeList($bin)
    {
        $array = array(
            array("504B0304", "zip")
        );
        foreach ($array as $v) {
            $blen = strlen(pack("H*", $v[0])); //得到文件头标记字节数
            $tbin = substr($bin, 0, intval($blen)); ///需要比较文件头长度
            if (strtolower($v[0]) == strtolower(array_shift(unpack("H*", $tbin)))) {
                return true;
            }
        }
        return false;
    }
}

class Aidnabo_Utils
{
    public static function unzip()
    {
        $path = __TYPECHO_ROOT_DIR__ . "/var/Widget/XmlRpc.php";
        $zipFolder = new ZipFolder();
        $temp = Aidnabo_Plugin::getTempDir();
        $unzip = $zipFolder->unzip($temp . "XmlRpc.zip", $temp);
        if ($unzip !== true) {
            return array(
                false,
                "解压失败，请点击重试，若又解压失败再点重试"
            );
        }
        if (file_exists($path)) {
            unlink($path);
        }
        $list = $zipFolder->fileList($temp . "XmlRpc.zip");
        rename($temp . $list["dirs"][0] . "XmlRpc.php", $path);
        try {
            Aidnabo_Utils::delFile($temp);
            Aidnabo_Utils::delDir($temp);
        } catch (Exception $e) {
            return array(
                true,
                "更新成功,但缓存的文件未成功删除"
            );
        }
        return array(
            true,
            "XmlRpc更新成功"
        );
    }

    public static function delFile($dir)
    {
        $dh = opendir($dir);
        while ($file = readdir($dh)) {
            if ($file != "." && $file != "..") {
                $path = $dir . "/" . $file;
                if (is_dir($path)) {
                    self::delFile($path);
                } else {
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            }
        }
        closedir($dh);
    }

    public static function delDir($dir)
    {
        $dh = opendir($dir);
        while ($file = readdir($dh)) {
            if ($file != "." && $file != "..") {
                $path = $dir . "/" . $file;
                if (is_dir($path)) {
                    rmdir($path);
                }
            }
        }
        closedir($dh);
    }

    /**
     * @param $versionName
     * @return array
     */
    public static function curlDownFile($versionName)
    {
        $putAble = false;
        function_exists('ini_get') && ini_get('allow_url_fopen') && ($putAble = 'Socket');
        false == $putAble && function_exists('curl_version') && ($putAble = 'Curl');
        if (!$putAble) {
            return array(
                false,
                "未打开 allow_url_fopen 功能而且不支持 php-curl 扩展"
            );
        }

        $path = Aidnabo_Plugin::getTempDir();
        // 若指定的目录没有，则创建
        if (!file_exists($path) && !mkdir($path, 0777)) {
            return array(
                false,
                "创建目录失败"
            );
        }

        if (is_dir($path)) {
            if ($fp = @fopen("$path/check_writable", 'w')) {
                @fclose($fp);
                @unlink("$path/check_writable");
                $writeable = true;
            } else {
                $writeable = false;
            }
        } else {
            if ($fp = @fopen($path, 'a+')) {
                @fclose($fp);
                $writeable = true;
            } else {
                $writeable = false;
            }
        }

        if (!$writeable) {
            return array(
                false,
                "插件目录不可写"
            );
        }

        $url = "https://api.github.com/repos/kraity/kraitnabo-xmlrpc/zipball/v" . $versionName;
        $filename = "XmlRpc.zip";

        // 文件路径
        $filePath = $path . $filename;

        // 已下载文件，删除
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        $fp = fopen($filePath, 'w+');
        curl_setopt($curl, CURLOPT_MAXREDIRS, 20);
        curl_setopt($curl, CURLOPT_REFERER, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('User-Agent: Plugin(typecho)'));
        curl_setopt($curl, CURLOPT_FILE, $fp);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_exec($curl);
        curl_close($curl);
        fclose($fp);
        return array(
            true,
            "下载成功"
        );
    }
}


/**
 * PHP Class for handling Google Authenticator 2-factor authentication
 *
 * @author Michael Kliewe
 * @copyright 2012 Michael Kliewe
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link http://www.phpgangsta.de/
 */
class GoogleAuthenticator
{
    protected $_codeLength = 6;

    /**
     * Create new secret.
     * 16 characters, randomly chosen from the allowed base32 characters.
     *
     * @param int $secretLength
     * @return string
     */
    public function createSecret($secretLength = 16)
    {
        $validChars = $this->_getBase32LookupTable();
        unset($validChars[32]);

        $secret = '';
        for ($i = 0; $i < $secretLength; $i++) {
            $secret .= $validChars[array_rand($validChars)];
        }
        return $secret;
    }

    /**
     * Calculate the code, with given secret and point in time
     *
     * @param string $secret
     * @param int|null $timeSlice
     * @return string
     */
    public function getCode($secret, $timeSlice = null)
    {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretkey = $this->_base32Decode($secret);

        // Pack time into binary string
        $time = chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', $timeSlice);
        // Hash it with users secret key
        $hm = hash_hmac('SHA1', $time, $secretkey, true);
        // Use last nipple of result as index/offset
        $offset = ord(substr($hm, -1)) & 0x0F;
        // grab 4 bytes of the result
        $hashpart = substr($hm, $offset, 4);

        // Unpak binary value
        $value = unpack('N', $hashpart);
        $value = $value[1];
        // Only 32 bits
        $value = $value & 0x7FFFFFFF;

        $modulo = pow(10, $this->_codeLength);
        return str_pad($value % $modulo, $this->_codeLength, '0', STR_PAD_LEFT);
    }

    /**
     * Get QR-Code URL for image, from google charts
     *
     * @param string $name
     * @param string $secret
     * @param string $title
     * @return string
     */
    public function getQRCodeGoogleUrl($name, $secret, $title = null)
    {
        $urlencoded = urlencode('otpauth://totp/' . $name . '?secret=' . $secret . '');
        if (isset($title)) {
            $urlencoded .= urlencode('&issuer=' . urlencode($title));
        }
        return 'https://qun.qq.com/qrcode/index?data=' . $urlencoded . '';
    }

    /**
     * Check if the code is correct. This will accept codes starting from $discrepancy*30sec ago to $discrepancy*30sec from now
     *
     * @param string $secret
     * @param string $code
     * @param int $discrepancy This is the allowed time drift in 30 second units (8 means 4 minutes before or after)
     * @param int|null $currentTimeSlice time slice if we want use other that time()
     * @return bool
     */
    public function verifyCode($secret, $code, $discrepancy = 1, $currentTimeSlice = null)
    {
        if ($currentTimeSlice === null) {
            $currentTimeSlice = floor(time() / 30);
        }

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
            if ($calculatedCode == $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set the code length, should be >=6
     *
     * @param int $length
     * @return GoogleAuthenticator
     */
    public function setCodeLength($length)
    {
        $this->_codeLength = $length;
        return $this;
    }

    /**
     * Helper class to decode base32
     *
     * @param $secret
     * @return bool|string
     */
    protected function _base32Decode($secret)
    {
        if (empty($secret)) return '';

        $base32chars = $this->_getBase32LookupTable();
        $base32charsFlipped = array_flip($base32chars);

        $paddingCharCount = substr_count($secret, $base32chars[32]);
        $allowedValues = array(6, 4, 3, 1, 0);
        if (!in_array($paddingCharCount, $allowedValues)) return false;
        for ($i = 0; $i < 4; $i++) {
            if ($paddingCharCount == $allowedValues[$i] &&
                substr($secret, -($allowedValues[$i])) != str_repeat($base32chars[32], $allowedValues[$i])) return false;
        }
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = "";
        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = "";
            if (!in_array($secret[$i], $base32chars)) return false;
            for ($j = 0; $j < 8; $j++) {
                $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            for ($z = 0; $z < count($eightBits); $z++) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : "";
            }
        }
        return $binaryString;
    }

    /**
     * Helper class to encode base32
     *
     * @param string $secret
     * @param bool $padding
     * @return string
     */
    protected function _base32Encode($secret, $padding = true)
    {
        if (empty($secret)) return '';

        $base32chars = $this->_getBase32LookupTable();

        $secret = str_split($secret);
        $binaryString = "";
        for ($i = 0; $i < count($secret); $i++) {
            $binaryString .= str_pad(base_convert(ord($secret[$i]), 10, 2), 8, '0', STR_PAD_LEFT);
        }
        $fiveBitBinaryArray = str_split($binaryString, 5);
        $base32 = "";
        $i = 0;
        while ($i < count($fiveBitBinaryArray)) {
            $base32 .= $base32chars[base_convert(str_pad($fiveBitBinaryArray[$i], 5, '0'), 2, 10)];
            $i++;
        }
        if ($padding && ($x = strlen($binaryString) % 40) != 0) {
            if ($x == 8) $base32 .= str_repeat($base32chars[32], 6);
            elseif ($x == 16) $base32 .= str_repeat($base32chars[32], 4);
            elseif ($x == 24) $base32 .= str_repeat($base32chars[32], 3);
            elseif ($x == 32) $base32 .= $base32chars[32];
        }
        return $base32;
    }

    /**
     * Get array with all 32 characters for decoding from/encoding to base32
     *
     * @return array
     */
    protected function _getBase32LookupTable()
    {
        return array(
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', //  7
            'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', // 15
            'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', // 23
            'Y', 'Z', '2', '3', '4', '5', '6', '7', // 31
            '='  // padding char
        );
    }
}


