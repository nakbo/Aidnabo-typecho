<?php
include 'common.php';
include 'header.php';
include 'menu.php';
$db = Typecho_Db::get();
$select = $db->select('table.users.password')
    ->from('table.users')
    ->where("uid = ?", Typecho_Cookie::get('__typecho_uid'));
$pwd = $db->fetchAll($select)[0]['password'];
$securityPwd = md5($pwd);

$options = Helper::options();
$personalOption = Aidnabo_Plugin::getConfigList(array(), false);
$option = Helper::options()->plugin('Aidnabo');
?>
<style type="text/css">
    .hide {
        color: blue;
        cursor: pointer;
    }

    .show {
        color: red;
        display: none;
    }
</style>
<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">

            <div class="col-mb-12">
                <ul class="typecho-option-tabs fix-tabs clearfix">
                    <li class="current"><a href="#">主界面</a></li>
                    <li>
                        <a href="https://docs.nabo.krait.cn/" target="_blank">使用文档</a>
                    </li>
                    <li>
                        <a href="<?php $options->adminUrl("profile.php#personal-Aidnabo"); ?>">安全设置</a>
                    </li>
                    <li>
                        <a href="<?php $options->adminUrl("options-plugin.php?config=Aidnabo"); ?>">助手设置</a>
                    </li>
                </ul>
            </div>

            <div class="col-mb-12 typecho-list">
                <form method="post" class="operate-form">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <colgroup>
                                <col width="25%">
                                <col width="45%">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>名称</th>
                                <th>值</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $aar = array();
                            $list = array(
                                "engineName" => "引擎",
                                "versionCode" => "XR代号",
                                "versionName" => "XR版本",
                            );
                            if (class_exists("Widget_XmlRpc")) {
                                $rm = new ReflectionMethod("Widget_XmlRpc", "NbGetManifestStatic");
                                if ($rm->isStatic()) {
                                    $aar = Widget_XmlRpc::NbGetManifestStatic();
                                }
                            }
                            foreach ($aar as $key => $value) {
                                ?>
                                <tr>
                                    <td><?php echo $list[$key]; ?></td>
                                    <td><?php echo $value; ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                            <tr>
                                <td>当前账户</td>
                                <td><?php echo Typecho_Widget::widget('Widget_User')->screenName; ?></td>
                            </tr>
                            <tr>
                                <td>[当前账户] Union</td>
                                <td>
                                    <span class="hide">点击显示</span>
                                    <span class="show"><?php echo $personalOption['union']; ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td>[当前账户] UNION匹配登录开关</td>
                                <td>
                                    <span class="hide"><?php echo $personalOption['unionSafe'] == 0 ? "已关闭" : "已打开"; ?></span>
                                    <span class="show"><a
                                                href="<?php $options->adminUrl("profile.php#personal-Aidnabo"); ?>"
                                                target="_blank">点击前往设置</a></span>
                                </td>
                            </tr>
                            <tr>
                                <td>[当前账户] RPC安全登录开关</td>
                                <td>
                                    <span class="hide"><?php echo $personalOption['rpcSafe'] == 0 ? "已关闭" : "已打开"; ?></span>
                                    <span class="show"><a
                                                href="<?php $options->adminUrl("profile.php#personal-Aidnabo"); ?>"
                                                target="_blank">点击前往设置</a></span>
                                </td>
                            </tr>
                            <tr>
                                <td>[当前账户] RPC安全登陆密码</td>
                                <td>
                                    <span class="hide">点击显示</span>
                                    <span class="show"><?php echo $securityPwd; ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td>[当前账户] GoogleAuth密匙</td>
                                <td>
                                    <span class="hide">点击显示</span>
                                    <span class="show"><?php echo $personalOption['gauthKey']; ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td>[当前账户] GoogleAuth开关</td>
                                <td>
                                    <span class="hide"><?php echo $personalOption['gauthSafe'] == 0 ? "已关闭" : "已打开"; ?></span>
                                    <span class="show"><a
                                                href="<?php $options->adminUrl("profile.php#personal-Aidnabo"); ?>"
                                                target="_blank">点击前往设置</a></span>
                                </td>
                            </tr>
                            <tr>
                                <td>RPC密匙</td>
                                <td>
                                    <span class="hide">点击显示</span>
                                    <span class="show"><?php echo $option->rpcKey; ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td>RPC更新开关</td>
                                <td>
                                    <span class="hide"><?php echo $option->rpcUpdateSafe == 0 ? "已关闭" : "已打开"; ?></span>
                                    <span class="show"><a
                                                href="<?php $options->adminUrl("options-plugin.php?config=Aidnabo"); ?>"
                                                target="_blank">点击前往设置</a></span>
                                </td>
                            </tr>
                            <tr>
                                <td>XmlRpc 接口</td>
                                <td>
                                    <span class="hide"><?php echo $options->markdown == 0 ? "已关闭" : "已打开"; ?></span>
                                    <span class="show"><a
                                                href="<?php $options->adminUrl("options-general.php#writing-option"); ?>"
                                                target="_blank">点击前往设置</a></span>
                                </td>
                            </tr>
                            <tr>
                                <td>使用 Markdown 语法编辑和解析内容</td>
                                <td>
                                    <span class="hide"><?php echo $options->xmlrpcMarkdown == 0 ? "已关闭" : "已打开"; ?></span>
                                    <span class="show"><a
                                                href="<?php $options->adminUrl("profile.php#writing-option"); ?>"
                                                target="_blank">点击前往设置</a></span>
                                </td>
                            </tr>
                            <tr>
                                <td>在 XmlRpc 接口中使用 Markdown 语法</td>
                                <td>
                                    <span class="hide"><?php echo $options->xmlrpcMarkdown == 0 ? "已关闭" : "已打开"; ?></span>
                                    <span class="show"><a
                                                href="<?php $options->adminUrl("profile.php#writing-option"); ?>"
                                                target="_blank">点击前往设置</a></span>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
?>

<script type="text/javascript">
    $(function () {
        $('.hide').on('click', function () {
            $(this).hide().parent().find('.show').show();
        });

        $('.show').on('click', function () {
            $(this).hide().parent().find('.hide').show();
        });
    });
</script>