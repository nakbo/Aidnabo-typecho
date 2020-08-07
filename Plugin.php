<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 南博助手  - XmlRpc更新、接口、后台安全
 *
 * @package Aidnabo
 * @author 权那他
 * @version 1.1
 * @link https://github.com/kraity/typecho-aidnabo
 */
class Aidnabo_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件
     * @return string|void
     * @throws Typecho_Db_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_User')->hashValidate = array("Aidnabo_Action", 'hashValidate');
        Typecho_Plugin::factory('admin/footer.php')->end = array("Aidnabo_Action", 'GoogleAuthLogin');
        Helper::addRoute("XmlRpc_Upgrade", "/aidnabo/xmlrpc/upgrade", "Aidnabo_Action", 'upgrade');
        Helper::addPanel(1, 'Aidnabo/manage-aidnabo.php', '南博助手', '南博助手', 'administrator');

        if (!file_exists(Aidnabo_Plugin::getTempDir())) {
            mkdir(Aidnabo_Plugin::getTempDir(), 0777);
        }

        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $db->query('CREATE TABLE IF NOT EXISTS `' . $prefix . 'users_aid` (
		  `uid` int(11) unsigned NOT NULL,
		  `union` varchar(96) DEFAULT NULL,
		  `unionSafe` int(1) DEFAULT 0,
		  `gauthKey` varchar(128) DEFAULT NULL,
		  `gauthSafe` int(1) DEFAULT 0,
		  `rpcSafe` int(1) DEFAULT 0,
		  PRIMARY KEY (`uid`)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;');

        return _t('插件已经激活，需先配置插件信息！');
    }

    /**
     * 禁用插件
     * @throws Typecho_Db_Exception
     * @throws Typecho_Exception
     */
    public static function deactivate()
    {
        $config = Typecho_Widget::widget('Widget_Options')->plugin('Aidnabo');
        if ($config->isDrop == 1) {
            $db = Typecho_Db::get();
            $db->query("DROP TABLE `{$db->getPrefix()}users_aid`", Typecho_Db::WRITE);
        }
        Helper::removeRoute("XmlRpc_Upgrade");
        Helper::removePanel(1, 'Aidnabo/manage-aidnabo.php');

    }

    /**
     * 临时目录
     * @return string
     */
    public static function getTempDir()
    {
        return dirname(__FILE__) . "/temp/";
    }

    /**
     * 文件路径
     * @return string
     */
    public static function getFilePath()
    {
        return Aidnabo_Plugin::getTempDir() . "XmlRpc.zip";
    }

    /**
     * 下载链接
     * @param $versionName
     * @return string
     */
    public static function getZipUrl($versionName)
    {
        return "https://codeload.github.com/kraity/kraitnabo-xmlrpc/zip/v" . $versionName;
    }

    /**
     * 插件配置面板
     * @param Typecho_Widget_Helper_Form $form
     * @throws Typecho_Exception
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
        $list = Aidnabo_Plugin::getConfigList(array(), false);

        $union = new Typecho_Widget_Helper_Form_Element_Password(
            'union', null, NULL,
            'UNION', 'UNION，当你在南博用QQ授权登录后，你会看见自己的union，它在QQ平台是唯一的，你可以在南博里个人信息界面里查看');
        $form->addInput($union);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'unionSafe', array(
            '0' => '关闭',
            '1' => '打开',
        ), '0', 'Union匹配登录开关', '打开后同时填写上面的union, 南博登陆博客时 强制必须 经过QQ授权登录且与上面的Union相符合后，再验证密码是否正确。 可以这样理解，先验证判断是否是上面绑定的Union，然后再验证判断密码');
        $form->addInput($enable);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'rpcSafe', array(
            '0' => '关闭',
            '1' => '打开',
        ), '0', 'RPC安全登陆开关', '打开后, 南博登陆博客时 强制必须 使用安全密码登录，这样相对安全一点。安全密码是经过你账号的密码计算而来,可以前往南博助手页面查看。如果开启安全密码登录同时开启union匹配登录，这样的XmlRpc安全性特别强');
        $form->addInput($enable);

        $secret = new Typecho_Widget_Helper_Form_Element_Text(
            'gauthKey', null, null,
            'GoogleAuth密匙', '请保管好,如果要重置则把这一栏清空再保存就可以重置。在谷歌Authenticator添加账号。其中账号名是随便填写即只是个记号，而密匙就是这个密匙，或者扫码添加，<a href="javascript:;" id="ScanOtp" target="_blank">点击扫码添加</a>');
        $form->addInput($secret);

        $qr = 'otpauth://totp/' . urlencode('[' . $_SERVER['HTTP_HOST'] . '] : ' . Typecho_Widget::widget('Widget_User')->mail) . '?secret=' . $list['gauthKey'];
        $html = '
<script>
    window.onload = function () {
        document.getElementsByName("union")[0].value = "' . $list['union'] . '";
        document.getElementsByName("gauthKey")[0].value = "' . $list['gauthKey'] . '";
        document.getElementsByName("unionSafe")[' . $list['unionSafe'] . '].checked = true;
        document.getElementsByName("gauthSafe")[' . $list['gauthSafe'] . '].checked = true;
        document.getElementsByName("rpcSafe")[' . $list['rpcSafe'] . '].checked = true;
        document.getElementById("ScanOtp").href  = "https://qun.qq.com/qrcode/index?data=' . $qr . '";
    }
</script>';

        $element = new Typecho_Widget_Helper_Form_Element_Radio(
            'gauthSafe', array(
            '0' => '关闭',
            '1' => '开启'
        ), "0", _t('GoogleAuth开关'), 'GoogleAuth开关，开启后在 后台登陆 需要二步验证，即还要输入6位数的动态验证码。请勿随意启动，先看文档再启用。请勿随意启动，先看文档再启用。请勿随意启动，先看文档再启用。' . $html);
        $form->addInput($element);
    }

    /**
     * 配置列表
     * @param array $config
     * @param bool $isUpdate
     * @return array|mixed
     * @throws Typecho_Db_Exception
     */
    public static function getConfigList($config = array(), $isUpdate = false)
    {
        $uid = Typecho_Cookie::get('__typecho_uid');
        $db = Typecho_Db::get();
        $list = $db->fetchRow($db->select()->from('table.users_aid')->where("uid = ?", $uid));
        if (count($list) > 0) {
            if ($isUpdate) {
                if (empty($config['gauthKey'])) {
                    $list['gauthKey'] = self::GAuthCreateSecret();
                }
                $list = array(
                    'union' => $config['union'],
                    'unionSafe' => $config['unionSafe'],
                    'gauthKey' => $list['gauthKey'],
                    'gauthSafe' => $config['gauthSafe'],
                    'rpcSafe' => $config['rpcSafe']
                );
                $db->query($db->update('table.users_aid')->rows($list)->where('uid = ?', $uid));
            }
        } else {
            $gauthKey = self::GAuthCreateSecret();
            $list = array(
                'uid' => $uid,
                'union' => '',
                'unionSafe' => 0,
                'gauthKey' => $gauthKey,
                'gauthSafe' => 0,
                'rpcSafe' => 0
            );
            $db->query($db->insert('table.users_aid')->rows($list));
        }
        unset($list['uid']);
        return $list;
    }

    /**
     * 插件配置帮手
     * @param $config
     * @param $isInit
     * @throws Typecho_Db_Exception
     */
    public static function personalConfigHandle($config, $isInit)
    {
        self::getConfigList($config, !$isInit);
        if ($isInit) {
            Helper::configPlugin("Aidnabo", array(
                'union' => '',
                'unionSafe' => 0,
                'gauthKey' => '',
                'gauthSafe' => 0,
                'rpcSafe' => 0
            ), true);
        }
    }

    /**
     * GAuthCreateSecret
     * @return string
     */
    private static function GAuthCreateSecret()
    {
        require_once 'Utils.php';
        $Authenticator = new GoogleAuthenticator();
        return $Authenticator->createSecret();
    }

    /**
     * config
     * @param Typecho_Widget_Helper_Form $form
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $secret = new Typecho_Widget_Helper_Form_Element_Text(
            'rpcKey', null, md5(uniqid(microtime(true), true)),
            'RPC密匙', '请保管好，建议包含英文和数字，越复杂越好。当你在南博更新XmlRpc文件时候需要用到RPC密匙来验证权限');
        $form->addInput($secret->addRule('required', _t('XR密匙必填!')));

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'rpcUpdateSafe', array(
            '0' => '关闭',
            '1' => '打开',
        ), '1', 'RPC更新开关', '请选择是否启用XmlRpc自动更新能力,自动从Github仓库拉取。关闭后就在南博里就不能快速更新XmlRpc了。建议要更新时候就开启，更新过后关闭');
        $form->addInput($enable);

        $isDrop = new Typecho_Widget_Helper_Form_Element_Radio(
            'isDrop', array(
            '0' => '不删除',
            '1' => '删除',
        ), '0', '删数据表', '请选择是否在禁用插件时，删除日志数据表，这张表是对应每个账户的安全设置，此表是本插件创建的，为了增强XmlRpc接口安全性和后台登陆安全性。如果选择不删除，那么禁用后再次启用还是之前的安全数据(除RPC密匙外)就不用重新安全设置');
        $form->addInput($isDrop);
    }
}
