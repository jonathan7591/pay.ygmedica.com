function generateRandomElementName(length) {
    var chars = '0123456789abcdef';
    var randomString = '';
    for (var i = 0; i < length; i++) {
        var index = Math.floor(Math.random() * chars.length);
        randomString += chars[index];
    }
    return randomString;
}
Vue.component('epay-cascader', {
    props: {value:Array, options:Array, allnode:Boolean, required:Boolean, name:String, autofill:Object},
    data: function(){
        return {
            id: 'cascader' + generateRandomElementName(6),
            isupdate: false
        }
    },
    watch: {
        value: function (val) {
            if(this.isupdate){this.isupdate = false; return;}
            $('#'+this.id).zdCascader('initFormData', val);
        }
    },
    mounted() {
        var _this = this;
        $('#'+this.id).zdCascader({
            data: this.options,
            container: '#'+this.id,
            search: false,
            onChange: function(value, label, datas){
                _this.isupdate = true;
                if(_this.allnode){
                    var result = [];
                    datas.forEach(function(item){
                        result.push(item.value);
                    });
                    _this.$emit('input', result)
                }else{
                    _this.$emit('input', datas.length > 0 ? datas[0].value : null)
                }
                if(_this.autofill && label){
                    var res = {};
                    Object.keys(_this.autofill).forEach((key) => {
                        if(_this.autofill[key] == 'label' && _this.allnode){
                            var result = [];
                            datas.forEach(function(item){
                                result.push(item.label);
                            });
                            res[key] = result;
                        }else{
                            res[key] = label[_this.autofill[key]];
                        }
                    })
                    _this.$emit('update-input', res)
                }
                $("#form-store").data("bootstrapValidator").resetField(_this.name);
            },
            initData: this.value
        });
    },
    template: '<input type="text" class="form-control" :id="id" :name="name" readonly :required="required" placeholder="请选择"/>'
})
Vue.component('epay-date-picker', {
    props: {value:String, longterm:Boolean, required:Boolean, disabled:Boolean, placeholder:String, name:String},
    data: function(){
        return {
            id: 'date' + generateRandomElementName(6),
            isLongterm: false
        }
    },
    watch: {
        value: function (val) {
            $('#'+this.id).datepicker('update', val);
        },
        isLongterm: function (val) {
            if(val){
                this.$emit('input', '长期')
            }else{
                this.$emit('input', '')
            }
        }
    },
    mounted() {
        var _this = this;
        $('#'+this.id).datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            clearBtn: true,
            language: 'zh-CN',
        }).on('changeDate', function(e) {
            _this.$emit('input', e.format())
            $("#form-store").data("bootstrapValidator").resetField(_this.name);
        });
    },
    template: `<div class="input-group"><input type="text" :value="value" :id="id" :name="name" class="form-control" :placeholder="placeholder" autocomplete="off" :disabled="isLongterm||disabled" :required="required"><span class="input-group-addon" v-if="longterm"><input type="checkbox" v-model="isLongterm" :disabled="disabled"> 长期</span><span class="input-group-addon" v-if="!longterm"><i class="fa fa-calendar fa-fw"></i></span></div>`
})
Vue.component('epay-bankcard', {
    props: {value:String, autofill:Object, name:String, ischeck:Boolean},
    methods: {
        checkBankCard(e){
            if(!this.ischeck) return;
            var that=this;
            var cardno = e.target.value;
            let loading = layer.load(2, {shade:[0.1,'#fff']});
            $.ajax({
                type: "POST",
                url: "ajax_applyments.php?act=checkBankCard",
                data: {cardno: cardno},
                dataType: 'json',
                success: function(data) {
                    layer.close(loading);
                    if(data.code == 0){
                        if(that.autofill && data.data){
                            var res = {};
                            Object.keys(that.autofill).forEach((key) => {
                                res[key] = data.data[that.autofill[key]];
                            })
                            that.$emit('update-input', res)
                            $("#form-store").data("bootstrapValidator").resetField(_this.name);
                        }
                    }else{
                        layer.alert(data.msg, {icon: 2});
                    }
                },
                error: function(){
                    layer.close(loading);
                    layer.msg('服务器错误');
                }
            });
        }
    },
    template: `<input type="text" class="form-control" :name="name" :value="value" data-bv-digits="true" @change="checkBankCard">`
})
Vue.component('epay-image-upload', {
    props: {value:[String, Array], src:[String, Array], filetype:String, filesize:Number, autofill:Object, required:Boolean, disabled:Boolean, name:String, multiple:Number, fileparam:Object},
    data: function(){
        return {
            id: 'imageupload' + generateRandomElementName(6),
            imgsrc: '/assets/img/uploadok.png',
            filelist:[],
            isupdate: false
        }
    },
    mounted() {
        if(this.value){
            if(this.multiple > 0 && Array.isArray(this.value)){
                this.filelist = this.value.map((item) => {
                    if(this.src && Array.isArray(this.src)){
                        var index = this.value.findIndex((value) => value == item);
                        return {imgsrc: this.src[index], value: item}
                    }else{
                        return {imgsrc: this.imgsrc, value: item}
                    }
                })
            }else{
                if(this.src){
                    this.filelist = [{imgsrc: this.src, value: this.value}];
                }else{
                    this.filelist = [{imgsrc: this.imgsrc, value: this.value}];
                }
            }
        }else{
            this.filelist = [];
        }
    },
    watch: {
        value: function (val) {
            if(this.isupdate){this.isupdate = false; return;}
            if(val){
                if(this.multiple > 0 && Array.isArray(val)){
                    this.filelist = val.map((item) => {
                        if(this.src && Array.isArray(this.src)){
                            var index = this.value.findIndex((value) => value == item);
                            return {imgsrc: this.src[index], value: item}
                        }else{
                            return {imgsrc: this.imgsrc, value: item}
                        }
                    })
                }else{
                    if(this.src){
                        this.filelist = [{imgsrc: this.src, value: this.value}];
                    }else{
                        this.filelist = [{imgsrc: this.imgsrc, value: this.value}];
                    }
                }
            }else{
                this.filelist = [];
            }
        }
    },
    methods: {
        selectFile(e){
            var that=this;
            var total = e.target.files.length;
            if(total == 0) return;
            var fileObj = e.target.files[0];
            if(fileObj.size <= 0) return;
            if(!this.filesize) this.filesize = 5;
            if(fileObj.size > 1024*1024*this.filesize){
                layer.alert('上传的图片不能大于'+this.filesize+'M');
                return;
            }
            var formData = new FormData();
            formData.append("cid", $("#cid").val());
            formData.append("type", this.filetype);
            formData.append("file", fileObj);
            if(this.fileparam){
                Object.keys(this.fileparam).forEach((key) => {
                    formData.append(key, this.fileparam[key]);
                })
            }
            let loading = layer.msg('正在上传中', {icon: 16,shade: 0.1,time: 0});
            $.ajax({
                url: "ajax_applyments.php?act=uploadImage",
                data: formData,
                type: "POST",
                dataType: "json",
                cache: false,
                processData: false,
                contentType: false,
                success: function (data) {
                    layer.close(loading);
                    if(data.code == 0){
                        layer.msg('上传图片成功', {time:800, icon:1});
                        that.isupdate = true;
                        var imgsrc = URL.createObjectURL(fileObj);
                        that.filelist.push({imgsrc: imgsrc, value: data.image_id})
                        if(that.multiple > 0){
                            that.$emit('input', that.filelist.map((item) => item.value))
                        }else{
                            that.$emit('input', data.image_id)
                        }
                        if(that.autofill && data.data){
                            var res = {};
                            Object.keys(that.autofill).forEach((key) => {
                                res[key] = data.data[that.autofill[key]];
                            })
                            that.$emit('update-input', res)
                            $("#form-store").data("bootstrapValidator").resetField(_this.name);
                        }
                    }else{
                        layer.alert(data.msg, {icon:2});
                        $('#'+that.id).val('')
                    }
                },
                error:function(){
                    layer.close(loading);
                    layer.msg('服务器错误');
                }
            })
        },
        deleteFile(index){
            var that=this;
            if(this.disabled) return;
            layer.confirm('确定删除该图片吗？', function(i){
                layer.close(i);
                that.isupdate = true;
                if(that.multiple > 0){
                    that.filelist.splice(index, 1);
                    that.$emit('input', that.filelist.map((item) => item.value))
                }else{
                    that.filelist = [];
                    that.$emit('input', '')
                }
                $('#'+that.id).val('')
            });
        },
    },
    template: `<div class="upload-image"><div class="upload-complete" v-for="(file,index) in filelist"><img :src="file.imgsrc"><span title="文件标识">{{file.value}}</span><a @click="deleteFile(index)" href="javascript:;" title="删除图片"><i class="fa fa-trash"></i></a></div><div class="upload-btn" v-show="!value || filelist.length<multiple"><input :id="id" :name="name" type="file" accept="image/*" :required="required" :disabled="disabled" @change="selectFile"><a><i class="fa fa-upload fa-fw"></i>上传</a></div><div class="multiple-note" v-show="multiple>0 && filelist.length<multiple">（最多上传{{multiple}}张）</div></div>`
})
Vue.component('epay-file-upload', {
    props: {value:String, filetype:String, filesize:Number, accept:String, required:Boolean, disabled:Boolean, name:String, fileparam:Object},
    data: function(){
        return {
            id: 'fileupload' + generateRandomElementName(6),
        }
    },
    methods: {
        selectFile(e){
            var that=this;
            var total = e.target.files.length;
            if(total == 0) return;
            var fileObj = e.target.files[0];
            if(fileObj.size <= 0) return;
            if(!this.filesize) this.filesize = 5;
            if(fileObj.size > 1024*1024*this.filesize){
                layer.alert('上传的图片不能大于'+this.filesize+'M');
                return;
            }
            var formData = new FormData();
            formData.append("cid", $("#cid").val());
            formData.append("type", this.filetype);
            formData.append("file", fileObj);
            if(this.fileparam){
                Object.keys(this.fileparam).forEach((key) => {
                    formData.append(key, this.fileparam[key]);
                })
            }
            let loading = layer.msg('正在上传中', {icon: 16,shade: 0.1,time: 0});
            $.ajax({
                url: "ajax_applyments.php?act=uploadFile",
                data: formData,
                type: "POST",
                dataType: "json",
                cache: false,
                processData: false,
                contentType: false,
                success: function (data) {
                    layer.close(loading);
                    if(data.code == 0){
                        layer.msg('上传文件成功', {time:800, icon:1});
                        that.$emit('input', data.file_id)
                    }else{
                        layer.alert(data.msg, {icon:2});
                        $('#'+that.id).val('')
                    }
                },
                error:function(){
                    layer.close(loading);
                    layer.msg('服务器错误');
                }
            })
        },
        deleteFile(){
            var that=this;
            if(this.disabled) return;
            layer.confirm('确定删除该文件吗？', function(index){
                layer.close(index);
                that.$emit('input', '')
                $('#'+that.id).val('')
            });
        },
    },
    template: `<div class="upload-file"><div class="upload-btn" v-show="!value"><input :id="id" :name="name" type="file" :accept="accept" :required="required" :disabled="disabled" @change="selectFile"><a class="btn btn-default btn-sm"><i class="fa fa-upload fa-fw"></i>上传</a></div><div class="upload-complete" v-show="value"><span title="文件标识"><i class="fa fa-file fa-fw"></i>{{value}}</span><a @click="deleteFile" href="javascript:;" title="删除文件"><i class="fa fa-trash"></i></a></div></div>`
})
Vue.component('epay-select', {
    props: {value:[String,Number], options:Array, placeholder:String, name:String},
    data: function(){
        return {
            id: 'select' + generateRandomElementName(6),
        }
    },
    template: `
    <select class="form-control selector" :id="id" :name="name" >
        <slot></slot>
    </select>
    `,
    mounted: function () {
        var vm = this;
        $('#'+this.id).select2({
            language: "zh-CN",
            placeholder: this.placeholder,
        }).val(this.value)
        .trigger('change')
        .on('change', function () {
            vm.$emit('input', this.value)
        })
    },
    watch: {
        value: function (value) {
            $('#'+this.id).val(value).trigger('change');
        },
        options: function (options) {
            $('#'+this.id).empty().select2({ data: options })
        }
    },
    destroyed: function () {
        $('#'+this.id).off().select2('destroy')
    }
})
Vue.component('epay-bank-select', {
    props: {value:[String,Number], placeholder:String, name:String, autofill:Object},
    data: function(){
        return {
            id: 'bankselect' + generateRandomElementName(6),
            isLoad: false,
        }
    },
    template: `
    <select class="form-control selector" :id="id" :name="name" >
    </select>
    `,
    mounted: function () {
        var vm = this;
        $('#'+this.id).select2({
            language: "zh-CN",
            placeholder: this.placeholder,
            ajax:{
                url: 'ajax_applyments.php?act=getBankList',
                type: "post",
                dataType: 'json',
                delay: 500,
                data: function(params) {
                    return {
                        cid: $("#cid").val(),
                        name: vm.name,
                        keyword: params.term,
                        page: params.page || 1,
                        limit: 10
                    };
                },
                processResults: function (data, params) {
                    if(data.code == 0){
                        vm.isLoad = true;
                        return data.data;
                    }else{
                        layer.alert(data.msg, {icon: 2});
                        return {
                            results: []
                        };
                    }
                }
            },
            cache:false
        })
        $('#'+this.id).on('change', function () {
            vm.$emit('input', this.value)
            if(vm.autofill){
                var data = $('#'+vm.id).select2('data')[0];
                if(data){
                    var res = {};
                    Object.keys(vm.autofill).forEach((key) => {
                        if(typeof data[vm.autofill[key]] != 'undefined') res[key] = data[vm.autofill[key]];
                    })
                    vm.$emit('update-input', res)
                }
            }
        })
        if(this.value){
            this.loadData(this.value);
        }
    },
    watch: {
        value: function (value) {
            if(this.isLoad){
                $('#'+this.id).val(value).trigger('change');
            }else{
                this.loadData(value);
            }
        }
    },
    methods: {
        loadData(value){
            var that = this;
            $.ajax({
                type: "POST",
                url: "ajax_applyments.php?act=getBankList",
                data: {cid: $("#cid").val(), name: that.name, keyid: value},
                dataType: 'json',
                success: function(data) {
                    if(data.code == 0 && data.data.results.length > 0){
                        var newOption = new Option(data.data.results[0].text, data.data.results[0].id, true, true);
                        $('#'+that.id).append(newOption).trigger('change');
                    }else{
                        layer.alert(data.msg, {icon: 2});
                    }
                }
            });
        }
    },
    destroyed: function () {
        $('#'+this.id).off().select2('destroy')
    }
})
Vue.component('epay-bank-branch-select', {
    props: {value:[String,Number], placeholder:String, name:String, required:Boolean, disabled:Boolean, bankCode:String, cityCode:[String,Number], autofill:Object},
    data: function(){
        return {
            id: 'bankbranchselect' + generateRandomElementName(6),
        }
    },
    template: `
    <div class="input-group"><select class="form-control selector" :id="id" :name="name" :required="required" :disabled="disabled"></select><span class="input-group-addon btn btn-default" @click="getBankBranchList">获取</span></div>
    `,
    mounted: function () {
        var vm = this;
        $('#'+this.id).select2({
            language: "zh-CN",
            placeholder: this.placeholder,
        })
        $('#'+this.id).on('change', function () {
            vm.$emit('input', this.value)
            if(vm.autofill){
                var data = $('#'+vm.id).select2('data')[0];
                if(data){
                    var res = {};
                    Object.keys(vm.autofill).forEach((key) => {
                        if(typeof data[vm.autofill[key]] != 'undefined') res[key] = data[vm.autofill[key]];
                    })
                    vm.$emit('update-input', res)
                }
            }
        })
    },
    methods: {
        getBankBranchList(){
            var that = this;
            if(!this.bankCode){
                layer.alert('请选择开户银行');
                return;
            }
            if(!this.cityCode){
                layer.alert('请选择开户银行省市');
                return;
            }
            if(typeof this.cityCode == 'object') this.cityCode = this.cityCode[0];
            let loading = layer.load(2, {shade:[0.1,'#fff']});
            $.ajax({
                type: "POST",
                url: "ajax_applyments.php?act=getBankBranchList",
                data: {cid: $("#cid").val(), bank_code: this.bankCode, city_code: this.cityCode},
                dataType: 'json',
                success: function(data) {
                    layer.close(loading);
                    if(data.code == 0){
                        layer.msg('成功获取到'+data.data.length+'个支行', {icon: 1, time:800});
                        $('#'+that.id).empty().select2({ data: data.data })
                        if(data.data.length > 0){
                            if(that.value && data.data.findIndex((item) => item.id == that.value) > -1){
                                $('#'+that.id).val(that.value);
                            }
                            $('#'+that.id).trigger('change');
                        }
                    }else{
                        layer.alert(data.msg, {icon: 2});
                    }
                },
                error: function(){
                    layer.close(loading);
                    layer.msg('服务器错误');
                }
            });
        }
    },
    destroyed: function () {
        $('#'+this.id).off().select2('destroy')
    }
})
Vue.component('epay-pay-channel-select', {
    props: {value:Array},
    data: function(){
        return {
            id: 'paychannelselect' + generateRandomElementName(6),
        }
    },
    template: `<select class="form-control selector" :id="id" multiple="multiple"></select>
    `,
    mounted: function () {
        var vm = this;
        $.ajax({
            type: "POST",
            url: "ajax_pay.php?act=getChannelsByPlugin&plugin=" + $("#plugin").val(),
            dataType: 'json',
            success: function(data) {
                var list = [];
                if(data.code == 0){
                    data.data.forEach(function(item){
                        list.push({id: item.id, text: item.name});
                    });
                    $('#'+vm.id).select2({
                        language: "zh-CN",
                        placeholder: this.placeholder,
                        data: list
                    })
                    if(vm.value){
                        $('#'+vm.id).val(vm.value).trigger('change');
                    }
                }else{
                    layer.alert(data.msg, {icon: 2});
                }
            }
        });
        
        $('#'+this.id).on('change', function (e) {
            vm.$emit('input', $('#'+this.id).val())
        })
    },
    destroyed: function () {
        $('#'+this.id).off().select2('destroy')
    }
})