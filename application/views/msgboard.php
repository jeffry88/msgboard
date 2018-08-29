<html>
<head>
<title>留言板</title>
<meta http-equiv="content-type" content="text/html;charset=utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no">
<link rel="stylesheet" href="<?php echo base_url()?>/public/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="<?php echo base_url()?>/public/css/style.css">
<link rel="stylesheet" href="<?php echo base_url()?>/public/layui/css/layui.css">
</head>
<body style="background: url('<?php echo base_url()?>/public/images/msbackground3.jpg')">
<div class="container">
    <div class="msgboder">
        <h1>留言板</h1>
        <div style="width: 50%;margin: 10px auto;">
            <form class="layui-form" action="<?php echo base_url('index.php/msgboard/setmessage')?>" method="post">
                <div class="layui-form-item">
                    <label class="layui-form-label">姓名</label>
                    <div class="layui-input-block">
                        <input onkeyup="value=value.replace(/[^\a-\z\A-\Z0-9\u4E00-\u9FA5]/g,'')"
                               onpaste="value=value.replace(/[^\a-\z\A-\Z0-9\u4E00-\u9FA5]/g,'')"
                               oncontextmenu = "value=value.replace(/[^\a-\z\A-\Z0-9\u4E00-\u9FA5]/g,'')"
                               type="text" name="user_name" id="user_name" required  lay-verify="required"
                               placeholder="请输入姓名" autocomplete="off" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item layui-form-text">
                    <label class="layui-form-label">留言</label>
                    <div class="layui-input-block">
                        <textarea onkeyup="value=value.replace(/[^\a-\z\A-\Z0-9\u4E00-\u9FA5\，\。\？\；]/g,'')"
                                  onpaste="value=value.replace(/[^\a-\z\A-\Z0-9\u4E00-\u9FA5\，\。\？\；]/g,'')"
                                  oncontextmenu = "value=value.replace(/[^\a-\z\A-\Z0-9\u4E00-\u9FA5\，\。\？\；]/g,'')"
                                  name="message" id="message" placeholder="请输入内容" class="layui-textarea"></textarea>
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-input-block">
                        <button class="layui-btn" lay-submit lay-filter="formDemo">留言</button>
                        <button type="reset" class="layui-btn layui-btn-primary">取消</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="message-list">
            <?php foreach ($messages as $row):?>
                <div id="main" class="main">
                    <ul>
                        <li class="user_name"><?php echo '用户名：', $row['user_name'] ?></li>
                        <li class="message"><?php echo $row['message'] ?></li>
                        <li><?php echo ' &nbsp 评论时间：', $row['time']?></li>
                    </ul>
                    <button id="layui-btn-xs" class="change" value="<?php echo $row['user_name']?>">编辑</button>
<!--                    <a href="--><?php //echo base_url('index.php/msgboard/delmessage')?><!--?&user_name=--><?php //echo $row['user_name']?><!--&">删除</a>-->
                    <button id="layui-btn-xs" class="del" value="<?php echo $row['user_name']?>">删除</button>
                </div>
            <?php endforeach;?>
        </div>
    </div>
    <div style="margin-left: 488px;">
        <?php echo $pager ?>
    </div>
</div>
<script src="<?php echo base_url()?>/public/bootstrap/js/jquery-3.3.1.min.js"></script>
<script src="<?php echo base_url()?>/public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url()?>/public/layui/layui.all.js"></script>
<script>
//Demo
layui.use('form', function(){
    var form = layui.form;

    //监听提交
    // form.on('submit(formDemo)', function(data){
    //     layer.msg('留言成功');
    //     //return false;
    //
    // });
});
</script>
<script>
//删除
$(document).ready(function() {
    $(".del").click(function (event) {
        event.preventDefault();
        var user_name = $(this).val();
        //alert(user_name);
        //alert("jeigu");
        $.ajax(
            {
                type:"post",
                url:"<?php echo base_url('index.php/msgboard/delmessage')?>",
                data:{"user_name":user_name},
                success:function (data) {
                    layer.msg('删除成功！', {
                        time: 0 //不自动关闭
                        ,btn: ['确定']
                        ,yes: function(index){
                            layer.close(index);
                            location.reload();
                        }
                    });
                }
                // error: function()
                // {
                //     alert("删除失败!");
                // }
            }
        );
    });
});
//将文本变为可编辑
$(document).ready(function() {
    $(".change").click(function (event) {
        event.preventDefault();
        var message = $(this).prev().find('.message').text();
        var user_name = $(this).val();
        //alert(user_name);
        input = $(' <input onkeyup="value=value.replace(/[^\\a-\\z\\A-\\Z0-9\u4E00-\u9FA5\\，\\。\\？\\；]/g,\'\')"\n' +
            '                               onpaste="value=value.replace(/[^\\a-\\z\\A-\\Z0-9\u4E00-\u9FA5\\，\\。\\？\\；]/g,\'\')"\n' +
            '                               oncontextmenu = "value=value.replace(/[^\\a-\\z\\A-\\Z0-9\u4E00-\u9FA5\\，\\。\\？\\；]/g,\'\')"\n' +
            '                               type="text" name="newmsg" class="newmsg" id="newmsg" required  lay-verify="required"\n' +
            '                               autocomplete="off" class="layui-input" value="'+message+'">' );
        $(this).prev().find('.message').replaceWith(input);
        newbtn = $('<button id="layui-btn-xs" class="updata" value="'+user_name+'">保存</button>');
        $(this).replaceWith(newbtn);
    });

    $('.main').on('click', '.updata', function(event)
    {
        event.preventDefault();
        var user_name = $(this).val();
        var message = $(this).prev().find('.newmsg').val();
        //alert(message);
        //alert("jeigu");
        $.ajax(
            {
                type:"post",
                url:"<?php echo base_url('index.php/msgboard/upmessage')?>",
                data:{"user_name":user_name,"message":message},
                success:function (data) {
                    layer.msg('修改成功！', {
                        time: 0 //不自动关闭
                        ,btn: ['确定']
                        ,yes: function(index){
                            layer.close(index);
                            location.reload();
                        }
                    });

                }
            }
        );
    });
});



</script>
</body>
</html>
