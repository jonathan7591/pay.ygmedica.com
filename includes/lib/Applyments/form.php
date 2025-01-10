<link href="<?php echo $cdnpublic?>select2/4.0.13/css/select2.min.css" rel="stylesheet"/>
<link href="/assets/css/datepicker.css" rel="stylesheet">
<link href="/assets/css/applyments.css?v=1005" rel="stylesheet"/>

<div class="panel panel-primary" id="app">
    <div class="panel-heading"><h3 class="panel-title"><?php echo $title?><span class="pull-right"><a class="btn btn-default btn-xs" @click="save_draft"><i class="fa fa-save"></i> 保存草稿</a><a class="btn btn-default btn-xs" @click="load_draft"><i class="fa fa-file-o"></i> 读取草稿</a></span></h3></div>
        <div class="panel-body" style="position: relative;">

<div class="el-loading-mask"><div class="el-loading-spinner"><svg viewBox="25 25 50 50" class="circular"><circle cx="50" cy="50" r="20" fill="none" class="path"></circle></svg></div></div>

<div class="steps" v-if="form.steps.length>1">
    <div v-for="(title,index) in form.steps" :class="{'step':true, 'step-finish':index<step, 'step-process':index==step, 'step-wait':index>step}">
        <div class="step-head">
            <div class="step-line"><i class="step-line-inner"></i></div>
            <div class="step-icon">{{index+1}}</div>
        </div>
        <div class="step-title">{{title}}</div>
    </div>
</div>

<form class="form-horizontal" id="form-store">
    <input type="hidden" name="action" id="action" value="<?php echo $action?>"/>
    <input type="hidden" name="id" id="id" value="<?php echo $id?>"/>
    <input type="hidden" name="cid" id="cid" value="<?php echo $cid?>"/>
    <input type="hidden" name="plugin" id="plugin" value="<?php echo $model->plugin?>"/>
    <div v-for="(items,index) in form.items" v-show="index==step">
        <div v-for="item in items" v-show="isShow(item.show)">
            <!-- alert -->
            <div class="form-group" v-if="item.type=='alert'">
                <div class="col-sm-offset-3 col-sm-7">
                    <div class="alert alert-dismissible" :class="{'alert-success':item.style=='success', 'alert-info':item.style=='info', 'alert-warning':item.style=='warning', 'alert-danger':item.style=='danger'}">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <span v-html="note(item.content)"></span>
                    </div>
                </div>
            </div>
            <!-- line -->
            <div class="form-group line-box" v-if="item.type=='line'">
                <div class="col-sm-offset-3 col-sm-7 line-item">
                    <h3>{{item.content}}</h3>
                </div>
            </div>
            <!-- input -->
            <div class="form-group" v-if="item.type=='input'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <input type="text" class="form-control" :name="item.name" v-model="set[item.name]" :placeholder="item.placeholder" :required="item.required" :disabled="item.disabled" :data-bv-id="item.validator=='id'" :data-bv-phone="item.validator=='phone'" :data-bv-numeric="item.validator=='numeric'" :data-bv-digits="item.validator=='digits'" :data-bv-integer="item.validator=='integer'" :data-bv-email="item.validator=='email'" :data-bv-uri="item.validator=='uri'" :min="item.min" :max="item.max"><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- bankcard -->
            <div class="form-group" v-if="item.type=='bankcard'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <epay-bankcard v-model="set[item.name]" :name="item.name" :ischeck="isShow(item.ischeck)" :autofill="item.autofill" :placeholder="item.placeholder" :required="item.required" :disabled="item.disabled" v-on:update-input="updateInput"></epay-bankcard><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- textarea -->
            <div class="form-group" v-if="item.type=='textarea'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <textarea class="form-control" :name="item.name" v-model="set[item.name]" :placeholder="item.placeholder" :required="item.required" :disabled="item.disabled"></textarea><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- select -->
            <div class="form-group" v-if="item.type=='select'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <select class="form-control" :name="item.name" v-model="set[item.name]" :required="item.required" :disabled="item.disabled" :placeholder="item.placeholder">
                        <option v-for="option in item.options" :value="option.value" v-show="isShow(option.show)">{{option.label}}</option>
                    </select><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- radio -->
            <div class="form-group" v-if="item.type=='radio'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <label class="radio-inline" v-for="option in item.options" v-show="isShow(option.show)">
                        <input type="radio" :name="item.name" :value="option.value" v-model="set[item.name]" :disabled="item.disabled"> {{option.label}}<span v-if="option.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="option.tip"><i class="fa fa-question-circle"></i></span>
                    </label><br/><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- checkbox -->
            <div class="form-group" v-if="item.type=='checkbox'">
                <div class="col-sm-offset-3 col-sm-7">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" :name="item.name" v-model="set[item.name]" :disabled="item.disabled"> {{item.label}}
                        </label> <span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span>
                    </div>
                </div>
            </div>
            <!-- checkboxes -->
            <div class="form-group" v-if="item.type=='checkboxes'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <label class="checkbox-inline" v-for="option in item.options" v-show="isShow(option.show)">
                        <input type="checkbox" :name="item.name" :value="option.value" v-model="set[item.name]" :disabled="item.disabled"> {{option.label}}<span v-if="option.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="option.tip"><i class="fa fa-question-circle"></i></span>
                    </label><br/><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- date -->
            <div class="form-group" v-if="item.type=='date'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <epay-date-picker v-model="set[item.name]" :name="item.name" :longterm="item.longterm" :placeholder="item.placeholder" :required="item.required" :disabled="item.disabled"></epay-date-picker><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- image-upload -->
            <div class="form-group" v-if="item.type=='image-upload'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <epay-image-upload v-model="set[item.name]" :src="item.src" :name="item.name" :filetype="item.filetype" :filesize="item.filesize" :autofill="item.autofill" :required="item.required" :disabled="item.disabled" :multiple="item.multiple" :fileparam="item.fileparam" v-on:update-input="updateInput"></epay-image-upload><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- file-upload -->
            <div class="form-group" v-if="item.type=='file-upload'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <epay-file-upload v-model="set[item.name]" :name="item.name" :filetype="item.filetype" :filesize="item.filesize" :required="item.required" :disabled="item.disabled" :multiple="item.multiple" :fileparam="item.fileparam" v-on:update-input="updateInput"></epay-file-upload><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- cascader -->
            <div class="form-group" v-if="item.type=='cascader'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <epay-cascader :options="item.options" v-model="set[item.name]" :name="item.name" :required="item.required" :disabled="item.disabled" :allnode="item.allnode" :autofill="item.autofill" v-on:update-input="updateInput"></epay-cascader><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- inputcity -->
            <div class="form-group" v-if="item.type=='inputcity'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <epay-cascader :options="citys" v-model="set[item.name]" :name="item.name" :required="item.required" :disabled="item.disabled" :allnode="item.allnode" :autofill="item.autofill" v-on:update-input="updateInput"></epay-cascader><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- bank-select -->
            <div class="form-group" v-if="item.type=='bank-select'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <epay-bank-select :name="item.name" v-model="set[item.name]" :required="item.required" :disabled="item.disabled" :placeholder="item.placeholder" :autofill="item.autofill" v-on:update-input="updateInput">
                    </epay-bank-select><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- bank-branch-select -->
            <div class="form-group" v-if="item.type=='bank-branch-select'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <epay-bank-branch-select :name="item.name" v-model="set[item.name]" :required="item.required" :disabled="item.disabled" :placeholder="item.placeholder" :bank-code="set[item.param.bank_code]" :city-code="set[item.param.city_code]" :autofill="item.autofill" v-on:update-input="updateInput">
                    </epay-bank-branch-select><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
            <!-- pay-channel-select -->
            <div class="form-group" v-if="item.type=='pay-channel-select'">
                <label class="col-sm-3 control-label no-padding-right" :is-required="item.required">{{item.label}}<span v-if="item.tip" class="tips" title="" data-toggle="tooltip" data-placement="bottom" :data-original-title="item.tip"><i class="fa fa-question-circle"></i></span></label>
                <div class="col-sm-7">
                    <epay-pay-channel-select :name="item.name" v-model="set[item.name]" :required="item.required" :disabled="item.disabled" :placeholder="item.placeholder" v-on:update-input="updateInput">
                    </epay-pay-channel-select><span v-if="item.note" class="input-note" v-html="note(item.note)"></span>
                </div>
            </div>
        </div>
    </div>
    <div class="form-group">
        <div class="col-sm-offset-3 col-sm-7 btngroup">
            <button v-if="step>0" type="button" class="btn btn-default" @click="laststep"><i class="fa fa-arrow-circle-o-left fa-fw"></i>上一步</button>
            <button v-if="step<form.steps.length-1" type="button" class="btn btn-primary" @click="nextstep"><i class="fa fa-arrow-circle-o-right fa-fw"></i>下一步</button>
            <button v-if="step==form.steps.length-1&&action!='view'" type="button" class="btn btn-primary" @click="submit"><i class="fa fa-check-circle-o fa-fw"></i>提交</button>
        </div>
    </div>
</form>
</div>
</div>