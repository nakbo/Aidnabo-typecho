<?php
/** @noinspection DuplicatedCode */

class Aidnabo_Utils
{
    /**
     * 解压，迁移，清理临时目录
     * @param $versionName
     * @return array
     */
    public static function unzip($versionName)
    {
        $phpZip = new ZipArchive();
        $filePath = Aidnabo_Plugin::getFilePath();
        $open = $phpZip->open($filePath, ZipArchive::CHECKCONS);
        if ($open !== true) {
            @unlink($filePath);
            return array(
                false,
                "压缩包校验错误"
            );
        }

        /** 解压至临时目录 */
        if (!$phpZip->extractTo(Aidnabo_Plugin::getTempDir())) {
            $error = error_get_last();
            return array(
                false,
                $error['message']
            );
        }
        $phpZip->close();

        /** 迁移 */
        $temp = Aidnabo_Plugin::getTempDir();
        $file = $temp . "kraitnabo-xmlrpc-" . $versionName . "/XmlRpc.php";
        $path = __TYPECHO_ROOT_DIR__ . "/var/Widget/XmlRpc.php";
        if (!file_exists($file)) {
            return array(
                false,
                "解压失败，请点击重试，若又解压失败再点重试"
            );
        }
        if (file_exists($path)) {
            unlink($path);
        }
        rename($file, $path);
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

    /**
     * 删除文件
     * @param $dir
     */
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

    /**
     * 删除目录
     * @param $dir
     */
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
     * 判断目录可写
     *
     * @access public
     * @param $dir
     * @return boolean
     */
    public static function isWrite($dir)
    {
        $testFile = "_test.txt";
        $fp = @fopen($dir . "/" . $testFile, "w");
        if (!$fp) {
            return false;
        }
        fclose($fp);
        $rs = @unlink($dir . "/" . $testFile);
        if ($rs) {
            return true;
        }
        return false;
    }

    /**
     * 下载
     * @param $versionName
     * @return array
     */
    public static function curlDownFile($versionName)
    {
        $client = Typecho_Http_Client::get();
        if (!$client) {
            return array(
                false,
                "未打开 allow_url_fopen 功能而且不支持 php-curl 扩展"
            );
        }

        if (!class_exists('ZipArchive')) {
            return array(
                false,
                "未安装ZipArchive扩展, 无法更新"
            );
        }

        if (!$client->isAvailable()) {
            return array(
                false,
                "Typecho_Http_Client 适配器不可用"
            );
        }

        // 若指定的目录没有，则创建
        $path = Aidnabo_Plugin::getTempDir();
        if (!file_exists($path) && !mkdir($path, 0777)) {
            return array(
                false,
                "创建目录失败"
            );
        }

        if (!self::isWrite($path)) {
            return array(
                false,
                "插件目录不可写"
            );
        }

        $filePath = Aidnabo_Plugin::getFilePath();
        // 已下载文件，删除
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        /** Typecho_Http_Client */
        $client->setHeader('User-Agent', $_SERVER['HTTP_USER_AGENT'])
            ->setTimeout(10)
            ->setMethod(Typecho_Http_Client::METHOD_GET);

        try {
            /** send */
            $client->send(
                Aidnabo_Plugin::getZipUrl($versionName)
            );
            $response = $client->getResponseBody();
            /** put */
            file_put_contents($filePath, $response);
            return array(
                true,
                "下载成功"
            );
        } catch (Typecho_Http_Client_Exception $e) {
            return array(false, $e);
        }
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


