<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 南博助手  - XmlRpc更新、接口、后台安全
 *
 * @package Aidnabo
 * @author 权那他
 * @version 1.2
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

        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        if ($db->fetchRow($db->query("SHOW TABLES LIKE '{$prefix}users_aid';"))) {
            /* 表更新 */
            $rows = $db->fetchRow($db->select()->from('table.users_aid'));
            $alter = array(
                "pushKey" => 'ALTER TABLE `' . $prefix . 'users_aid` ADD `pushKey` varchar(32) DEFAULT NULL;',
                "pushSafe" => 'ALTER TABLE `' . $prefix . 'users_aid` ADD `pushSafe` int(1) DEFAULT 0;'
            );
            foreach ($alter as $column => $query) {
                if (!array_key_exists($column, $rows)) {
                    $db->query($query);
                }
            }
        } else {
            $db->query('CREATE TABLE IF NOT EXISTS `' . $prefix . 'users_aid` (
		  `uid` int(11) unsigned NOT NULL,
		  `union` varchar(96) DEFAULT NULL,
		  `unionSafe` int(1) DEFAULT 0,
		  `pushKey` varchar(32) DEFAULT NULL,
		  `pushSafe` int(1) DEFAULT 0,
		  `gauthKey` varchar(128) DEFAULT NULL,
		  `gauthSafe` int(1) DEFAULT 0,
		  `rpcSafe` int(1) DEFAULT 0,
		  PRIMARY KEY (`uid`)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;');

        }

        if (!file_exists(Aidnabo_Plugin::getTempDir())) {
            mkdir(Aidnabo_Plugin::getTempDir(), 0777);
        }

        Typecho_Plugin::factory('Widget_User')->hashValidate = array("Aidnabo_Action", 'hashValidate');
        Typecho_Plugin::factory('admin/footer.php')->end = array("Aidnabo_Action", 'GoogleAuthLogin');
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array("Aidnabo_Plugin", "finishComment");

        Helper::addRoute("XmlRpc_Upgrade", "/aidnabo/xmlrpc/upgrade", "Aidnabo_Action", 'upgrade');
        Helper::addPanel(1, 'Aidnabo/manage-aidnabo.php', '南博助手', '南博助手', 'administrator');

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
            'Union', 'Union，当你在南博用QQ授权登录后，你会看见自己的union，它在QQ平台是唯一的，你可以在南博里个人信息界面里查看');
        $form->addInput($union);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'unionSafe', array(
            '0' => '关闭',
            '1' => '打开',
        ), '0', '匹配登录开关', '打开后同时填写上面的 union, 南博登陆博客时 强制必须 经过QQ授权登录且与上面的Union相符合后，再验证密码是否正确。 可以这样理解，先验证判断是否是上面绑定的Union，然后再验证判断密码');
        $form->addInput($enable);

        $pushKey = new Typecho_Widget_Helper_Form_Element_Text(
            'pushKey', null, NULL,
            '推送密匙', '推送密匙，32位小写字符串，南博会员功能。用于消息推送到南博，评论回复通知、自定义消息推送。');
        $form->addInput($pushKey);

        $pushSafe = new Typecho_Widget_Helper_Form_Element_Radio(
            'pushSafe', array(
            '0' => '关闭',
            '1' => '打开',
        ), '0', '推送开关', '打开后同时填写上面的推送密匙，用于消息推送到南博，一般延迟在5分钟，请勿频繁推送，否者将无法再使用');
        $form->addInput($pushSafe);

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

        $qr = 'otpauth://totp/' . urlencode(Typecho_Widget::widget('Widget_User')->mail . ' (' . $_SERVER['HTTP_HOST'] . ')') . '?secret=' . $list['gauthKey'];
        $html = '
<script>
    window.onload = function () {
        document.getElementsByName("union")[0].value = "' . $list['union'] . '";
        document.getElementsByName("gauthKey")[0].value = "' . $list['gauthKey'] . '";
        document.getElementsByName("pushKey")[0].value = "' . $list['pushKey'] . '";
        document.getElementsByName("unionSafe")[' . $list['unionSafe'] . '].checked = true;
        document.getElementsByName("pushSafe")[' . $list['pushSafe'] . '].checked = true;
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
                    'pushKey' => $config['pushKey'],
                    'pushSafe' => $config['pushSafe'],
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
                'pushKey' => '',
                'pushSafe' => 0,
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
                'pushKey' => '',
                'pushSafe' => 0,
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

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'commentPushAble', array(
            '0' => '关闭',
            '1' => '打开',
        ), '0', '评论通知总开关', '请选择是否启用评论通知，注意启用之前，需要填写推送密匙，推送开关。均开启后，当别人评论时，博客调用推送接口，推送到南博App，一般延迟在5分钟');
        $form->addInput($enable);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'setThemeAble', array(
            '0' => '禁止',
            '1' => '允许',
        ), '0', '主题设置能力', '开启后，在南博可以切换主题和配置主题');
        $form->addInput($enable);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'setPluginAble', array(
            '0' => '禁止',
            '1' => '允许',
        ), '0', '插件设置能力', '开启后，在南博启用和禁用插件以及配置插件');
        $form->addInput($enable);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'setOptionAble', array(
            '0' => '禁止',
            '1' => '允许',
        ), '0', '基本设置能力', '开启后，在南博进行基本设置、评论设置、阅读设置、永久链接设置、以及个人资料设置');
        $form->addInput($enable);

        $isDrop = new Typecho_Widget_Helper_Form_Element_Radio(
            'isDrop', array(
            '0' => '不删除',
            '1' => '删除',
        ), '0', '删数据表', '请选择是否在禁用插件时，删除日志数据表，这张表是对应每个账户的安全设置，此表是本插件创建的，为了增强XmlRpc接口安全性和后台登陆安全性。如果选择不删除，那么禁用后再次启用还是之前的安全数据(除本页面的配置外)就不用重新安全设置');
        $form->addInput($isDrop);
    }

    /**
     * @param array $request
     * @return bool
     * @throws Typecho_Db_Exception
     */
    public static function sendMessage($request)
    {
        $db = Typecho_Db::get();
        $list = $db->fetchRow($db->select()
            ->from('table.users_aid')
            ->where("uid = ?", $request["uid"]));

        if (count($list) > 0) {
            if ($list['pushSafe'] == 1) {
                $client = Typecho_Http_Client::get();
                if (false == $client) {
                    return false;
                }

                if (empty($list['union'])) {
                    return false;
                }

                if (empty($list['pushKey'])) {
                    return false;
                }

                if (empty($request['title'])) {
                    return false;
                }

                if (empty($request['text'])) {
                    return false;
                }

                if (!preg_match("/^[a-f0-9]{32}$/", $list['pushKey'])) {
                    return false;
                }

                try {
                    $client->setHeader('User-Agent', $_SERVER['HTTP_USER_AGENT'])
                        ->setTimeout(5)
                        ->setData(array(
                            'union' => $list['union'],
                            'secret' => $list['pushKey'],
                            'version' => 1,
                            'title' => $request['title'],
                            'text' => $request['text']
                        ))->send("https://api.krait.cn/nabo/push");

                    $response = $client->getResponseBody();
                    $body = json_decode($response);
                    return $body->state;
                } catch (Exception $e) {
                    return false;
                }
            }
            return false;
        }
        return false;
    }

    /**
     * 新评论通知，目前，通知博主自己
     * @param Widget_Comments_Edit|Widget_Feedback $comment
     * @throws Typecho_Db_Exception
     * @throws Typecho_Plugin_Exception
     */
    public static function finishComment($comment)
    {
        if (Helper::options()->plugin("Aidnabo")->commentPushAble == 1) {
            /** 作者自己评论就不通知 */
            if ($comment->authorId != $comment->ownerId) {
                Aidnabo_Plugin::sendMessage(array(
                    "uid" => $comment->ownerId,
                    "title" => "你有一条新" . ($comment->status == 'approved' ? "" : "待审核的") . "评论",
                    "text" => $comment->author . "在" . date("H点i分", $comment->created) . "给你留了言"
                ));
            }
        }
    }
}
