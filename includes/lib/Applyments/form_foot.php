<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script src="<?php echo $cdnpublic?>vue/2.6.14/vue.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.zh-CN.min.js"></script>
<script src="<?php echo $cdnpublic?>select2/4.0.13/js/select2.min.js"></script>
<script src="<?php echo $cdnpublic?>select2/4.0.13/js/i18n/zh-CN.min.js"></script>
<script src="/assets/js/bootstrapValidator.min.js"></script>
<?php if($model->type == 'AlipayDirect'||$model->type == 'Huolian'){?>
<script src="/assets/js/citys2.js"></script>
<?php }else{?>
<script src="/assets/js/citys.js"></script>
<?php }?>
<script src="/assets/js/cascader.js?v=1004"></script>
<script src="/assets/js/applyments.js?v=1008"></script>
<script>
new Vue({
    el: '#app',
    data: {
        action: $('#action').val(),
        step: 0,
        form: {
            steps: [],
            items: [],
        },
        set: {},
        citys: citys,
    },
    watch: {
        
    },
    created() {
        var that = this;
    },
    async mounted() {
        var that=this;
        await this.getFormData().then((data) => {
            data.items.forEach((items) => {
                items.forEach((item) => {
                    if(!item.name) return;
                    if(typeof item.value == 'undefined'){
                        if(item.type == 'checkbox'){
                            item.value = false; 
                        }else if(item.type == 'checkboxes'){
                            item.value = []; 
                        }else{
                            item.value = null;
                        }
                    }
                    that.$set(that.set, item.name, item.value)
                })
            });
            that.form = data;
        }).catch((msg) => {
            layer.alert(msg, {icon: 2});
        });

        $(".el-loading-mask").hide();

        if(this.action == 'create'){
            var key = 'applyments_temp_'+$('#cid').val();
            var temp = localStorage.getItem(key);
            if(temp){
                var data = JSON.parse(temp);
                if(data){
                    var confirm = layer.confirm('检测到您有未提交的数据，是否恢复后继续填写？', {
                        btn: ['确定', '取消']
                    }, function(){
                        layer.close(confirm);
                        Object.keys(data).forEach((key) => {
                            if(key!='order_id' && key!='uid' && key!='mch_id' && data[key]){
                                that.set[key] = data[key];
                            }
                        })
                        $("#form-store").data("bootstrapValidator").resetForm();
                        localStorage.removeItem(key);
                    }, function(){
                        layer.close(confirm);
                        localStorage.removeItem(key);
                    });
                }
            }
        }
        
        this.$nextTick(function () {
            $('[data-toggle="tooltip"]').tooltip();
            $("#form-store").bootstrapValidator({
                live: 'submitted',
            });
            $("input[name=uid]").blur(function(){
                var uid = $(this).val();
                if(uid){
                    $.ajax({
                        type: "GET",
                        url: "./ajax_user.php?act=checkuid",
                        data: {uid: uid},
                        dataType: 'json',
                        success: function(data) {
                            if(data.code == -1){
                                layer.alert(data.msg, {icon: 2});
                            }
                        }
                    });
                }
            });
        })
    },
    methods: {
        getFormData() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: "ajax_applyments.php?act=getFormData",
                    data: {action:$('#action').val(), cid: $('#cid').val(), id: $('#id').val()},
                    dataType: 'json',
                    success: function(data) {
                        if(data.code == 0){
                            resolve(data.data);
                        }else{
                            reject(data.msg);
                        }
                    }
                });
            });
        },
        updateInput(data){
            Object.keys(data).forEach((key) => {
                this.set[key] = data[key]
            })
        },
        laststep(){
            if(this.step > 0){
                this.step--;
                $("#form-store").data("bootstrapValidator").resetForm();
            }
        },
        nextstep(){
            $("#form-store").data("bootstrapValidator").validate();
            if(!$("#form-store").data("bootstrapValidator").isValid()){
                return;
            }
            if(this.step < this.form.steps.length - 1){
                this.step++;
                var key = 'applyments_temp_'+$('#cid').val();
                localStorage.setItem(key, JSON.stringify(this.set));
                $("#form-store").data("bootstrapValidator").resetForm();
            }
        },
        submit(){
            var that=this;
            $("#form-store").data("bootstrapValidator").validate();
            if(!$("#form-store").data("bootstrapValidator").isValid()){
                return;
            }
            var data = {
                action: $('#action').val(),
                id: $('#id').val(),
                cid: $('#cid').val(),
                data: JSON.stringify(this.set),
            }
            let loading = layer.msg('正在提交中', {icon: 16,shade: 0.1,time: 0});
            $.ajax({
                type: "POST",
                url: "ajax_applyments.php?act=submit",
                data: data,
                dataType: 'json',
                success: function(data) {
                    layer.close(loading);
                    if(data.code == 0){
                        var key = 'applyments_temp_'+$('#cid').val();
                        localStorage.removeItem(key);
                        layer.alert(data.msg, {icon: 1}, function(){
                            if(that.action == 'config') window.history.back();
                            else
                            window.location.href = 'applyments_merchant.php';
                        });
                    }else{
                        layer.alert(data.msg, {icon: 2});
                    }
                },
                error: function(data){
                    layer.close(loading);
                    layer.msg('服务器错误');
                }
            });
        },
        isShow(show){
            if(typeof show == 'boolean' && show){
                return show;
            }else if(typeof show == 'string' && show){
                var that=this;
                Object.keys(this.set).forEach((key) => {
                    show = show.replace(new RegExp(key, 'g'), 'that.set["'+key+'"]')
                })
                return eval(show);
            }else{
                return true;
            }
        },
        note(content){
            if(!content) return;
            var that=this;
            Object.keys(this.set).forEach((key) => {
                content = content.replace(/\${([^}]+)}/g, (match, key) => that.set[key] ?? '');
            })
            return content;
        },
        save_draft(){
            var cid = $('#cid').val();
            var key = 'applyments_draft_'+cid;
            localStorage.setItem(key, JSON.stringify(this.set));
            layer.msg('保存草稿成功', {icon: 1, time: 800, shade: 0.1});
        },
        load_draft(){
            var cid = $('#cid').val();
            var key = 'applyments_draft_'+cid;
            var draft = localStorage.getItem(key);
            if(draft){
                var data = JSON.parse(draft);
                if(data){
                    Object.keys(data).forEach((key) => {
                        if(key!='order_id' && key!='uid' && key!='mch_id' && data[key]){
                            this.set[key] = data[key];
                        }
                    })
                    layer.msg('读取草稿成功', {icon: 1, time: 800, shade: 0.1});
                    $("#form-store").data("bootstrapValidator").resetForm();
                    return;
                }
            }
            layer.msg('暂无草稿', {icon: 0, time: 800, shade: 0.1});
        }
    },
})
</script>