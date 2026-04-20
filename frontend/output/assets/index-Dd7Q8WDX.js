import{H as m,R as $,K as v,$ as w,a0 as T,o as g,c,b as f,a6 as h,n as A,X as d,j as V,t as S,aE as p,ay as b,k as I,F as z,g as C,m as K,aA as D,w as x}from"./index-CltWC9mn.js";import{b as k}from"./index-BnEUQty_.js";var E=({dt:t})=>`
.p-togglebutton {
    display: inline-flex;
    cursor: pointer;
    user-select: none;
    overflow: hidden;
    position: relative;
    color: ${t("togglebutton.color")};
    background: ${t("togglebutton.background")};
    border: 1px solid ${t("togglebutton.border.color")};
    padding: ${t("togglebutton.padding")};
    font-size: 1rem;
    font-family: inherit;
    font-feature-settings: inherit;
    transition: background ${t("togglebutton.transition.duration")}, color ${t("togglebutton.transition.duration")}, border-color ${t("togglebutton.transition.duration")},
        outline-color ${t("togglebutton.transition.duration")}, box-shadow ${t("togglebutton.transition.duration")};
    border-radius: ${t("togglebutton.border.radius")};
    outline-color: transparent;
    font-weight: ${t("togglebutton.font.weight")};
}

.p-togglebutton-content {
    display: inline-flex;
    flex: 1 1 auto;
    align-items: center;
    justify-content: center;
    gap: ${t("togglebutton.gap")};
    padding: ${t("togglebutton.content.padding")};
    background: transparent;
    border-radius: ${t("togglebutton.content.border.radius")};
    transition: background ${t("togglebutton.transition.duration")}, color ${t("togglebutton.transition.duration")}, border-color ${t("togglebutton.transition.duration")},
            outline-color ${t("togglebutton.transition.duration")}, box-shadow ${t("togglebutton.transition.duration")};
}

.p-togglebutton:not(:disabled):not(.p-togglebutton-checked):hover {
    background: ${t("togglebutton.hover.background")};
    color: ${t("togglebutton.hover.color")};
}

.p-togglebutton.p-togglebutton-checked {
    background: ${t("togglebutton.checked.background")};
    border-color: ${t("togglebutton.checked.border.color")};
    color: ${t("togglebutton.checked.color")};
}

.p-togglebutton-checked .p-togglebutton-content {
    background: ${t("togglebutton.content.checked.background")};
    box-shadow: ${t("togglebutton.content.checked.shadow")};
}

.p-togglebutton:focus-visible {
    box-shadow: ${t("togglebutton.focus.ring.shadow")};
    outline: ${t("togglebutton.focus.ring.width")} ${t("togglebutton.focus.ring.style")} ${t("togglebutton.focus.ring.color")};
    outline-offset: ${t("togglebutton.focus.ring.offset")};
}

.p-togglebutton.p-invalid {
    border-color: ${t("togglebutton.invalid.border.color")};
}

.p-togglebutton:disabled {
    opacity: 1;
    cursor: default;
    background: ${t("togglebutton.disabled.background")};
    border-color: ${t("togglebutton.disabled.border.color")};
    color: ${t("togglebutton.disabled.color")};
}

.p-togglebutton-label,
.p-togglebutton-icon {
    position: relative;
    transition: none;
}

.p-togglebutton-icon {
    color: ${t("togglebutton.icon.color")};
}

.p-togglebutton:not(:disabled):not(.p-togglebutton-checked):hover .p-togglebutton-icon {
    color: ${t("togglebutton.icon.hover.color")};
}

.p-togglebutton.p-togglebutton-checked .p-togglebutton-icon {
    color: ${t("togglebutton.icon.checked.color")};
}

.p-togglebutton:disabled .p-togglebutton-icon {
    color: ${t("togglebutton.icon.disabled.color")};
}

.p-togglebutton-sm {
    padding: ${t("togglebutton.sm.padding")};
    font-size: ${t("togglebutton.sm.font.size")};
}

.p-togglebutton-sm .p-togglebutton-content {
    padding: ${t("togglebutton.content.sm.padding")};
}

.p-togglebutton-lg {
    padding: ${t("togglebutton.lg.padding")};
    font-size: ${t("togglebutton.lg.font.size")};
}

.p-togglebutton-lg .p-togglebutton-content {
    padding: ${t("togglebutton.content.lg.padding")};
}
`,P={root:function(e){var n=e.instance,r=e.props;return["p-togglebutton p-component",{"p-togglebutton-checked":n.active,"p-invalid":n.$invalid,"p-togglebutton-sm p-inputfield-sm":r.size==="small","p-togglebutton-lg p-inputfield-lg":r.size==="large"}]},content:"p-togglebutton-content",icon:"p-togglebutton-icon",label:"p-togglebutton-label"},F=m.extend({name:"togglebutton",style:E,classes:P}),q={name:"BaseToggleButton",extends:k,props:{onIcon:String,offIcon:String,onLabel:{type:String,default:"Yes"},offLabel:{type:String,default:"No"},iconPos:{type:String,default:"left"},readonly:{type:Boolean,default:!1},tabindex:{type:Number,default:null},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null},size:{type:String,default:null}},style:F,provide:function(){return{$pcToggleButton:this,$parentInstance:this}}},B={name:"ToggleButton",extends:q,inheritAttrs:!1,emits:["change"],methods:{getPTOptions:function(e){var n=e==="root"?this.ptmi:this.ptm;return n(e,{context:{active:this.active,disabled:this.disabled}})},onChange:function(e){!this.disabled&&!this.readonly&&(this.writeValue(!this.d_value,e),this.$emit("change",e))},onBlur:function(e){var n,r;(n=(r=this.formField).onBlur)===null||n===void 0||n.call(r,e)}},computed:{active:function(){return this.d_value===!0},hasLabel:function(){return v(this.onLabel)&&v(this.offLabel)},label:function(){return this.hasLabel?this.d_value?this.onLabel:this.offLabel:" "}},directives:{ripple:$}},j=["tabindex","disabled","aria-pressed","aria-label","aria-labelledby","data-p-checked","data-p-disabled"];function N(t,e,n,r,i,o){var a=w("ripple");return T((g(),c("button",d({type:"button",class:t.cx("root"),tabindex:t.tabindex,disabled:t.disabled,"aria-pressed":t.d_value,onClick:e[0]||(e[0]=function(){return o.onChange&&o.onChange.apply(o,arguments)}),onBlur:e[1]||(e[1]=function(){return o.onBlur&&o.onBlur.apply(o,arguments)})},o.getPTOptions("root"),{"aria-label":t.ariaLabel,"aria-labelledby":t.ariaLabelledby,"data-p-checked":o.active,"data-p-disabled":t.disabled}),[f("span",d({class:t.cx("content")},o.getPTOptions("content")),[h(t.$slots,"default",{},function(){return[h(t.$slots,"icon",{value:t.d_value,class:A(t.cx("icon"))},function(){return[t.onIcon||t.offIcon?(g(),c("span",d({key:0,class:[t.cx("icon"),t.d_value?t.onIcon:t.offIcon]},o.getPTOptions("icon")),null,16)):V("",!0)]}),f("span",d({class:t.cx("label")},o.getPTOptions("label")),S(o.label),17)]})],16)],16,j)),[[a]])}B.render=N;var R=({dt:t})=>`
.p-selectbutton {
    display: inline-flex;
    user-select: none;
    vertical-align: bottom;
    outline-color: transparent;
    border-radius: ${t("selectbutton.border.radius")};
}

.p-selectbutton .p-togglebutton {
    border-radius: 0;
    border-width: 1px 1px 1px 0;
}

.p-selectbutton .p-togglebutton:focus-visible {
    position: relative;
    z-index: 1;
}

.p-selectbutton .p-togglebutton:first-child {
    border-inline-start-width: 1px;
    border-start-start-radius: ${t("selectbutton.border.radius")};
    border-end-start-radius: ${t("selectbutton.border.radius")};
}

.p-selectbutton .p-togglebutton:last-child {
    border-start-end-radius: ${t("selectbutton.border.radius")};
    border-end-end-radius: ${t("selectbutton.border.radius")};
}

.p-selectbutton.p-invalid {
    outline: 1px solid ${t("selectbutton.invalid.border.color")};
    outline-offset: 0;
}
`,H={root:function(e){var n=e.instance;return["p-selectbutton p-component",{"p-invalid":n.$invalid}]}},M=m.extend({name:"selectbutton",style:R,classes:H}),U={name:"BaseSelectButton",extends:k,props:{options:Array,optionLabel:null,optionValue:null,optionDisabled:null,multiple:Boolean,allowEmpty:{type:Boolean,default:!0},dataKey:null,ariaLabelledby:{type:String,default:null},size:{type:String,default:null}},style:M,provide:function(){return{$pcSelectButton:this,$parentInstance:this}}};function W(t,e){var n=typeof Symbol<"u"&&t[Symbol.iterator]||t["@@iterator"];if(!n){if(Array.isArray(t)||(n=L(t))||e){n&&(t=n);var r=0,i=function(){};return{s:i,n:function(){return r>=t.length?{done:!0}:{done:!1,value:t[r++]}},e:function(s){throw s},f:i}}throw new TypeError(`Invalid attempt to iterate non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}var o,a=!0,l=!1;return{s:function(){n=n.call(t)},n:function(){var s=n.next();return a=s.done,s},e:function(s){l=!0,o=s},f:function(){try{a||n.return==null||n.return()}finally{if(l)throw o}}}}function X(t){return J(t)||G(t)||L(t)||Y()}function Y(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function L(t,e){if(t){if(typeof t=="string")return y(t,e);var n={}.toString.call(t).slice(8,-1);return n==="Object"&&t.constructor&&(n=t.constructor.name),n==="Map"||n==="Set"?Array.from(t):n==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?y(t,e):void 0}}function G(t){if(typeof Symbol<"u"&&t[Symbol.iterator]!=null||t["@@iterator"]!=null)return Array.from(t)}function J(t){if(Array.isArray(t))return y(t)}function y(t,e){(e==null||e>t.length)&&(e=t.length);for(var n=0,r=Array(e);n<e;n++)r[n]=t[n];return r}var Q={name:"SelectButton",extends:U,inheritAttrs:!1,emits:["change"],methods:{getOptionLabel:function(e){return this.optionLabel?b(e,this.optionLabel):e},getOptionValue:function(e){return this.optionValue?b(e,this.optionValue):e},getOptionRenderKey:function(e){return this.dataKey?b(e,this.dataKey):this.getOptionLabel(e)},isOptionDisabled:function(e){return this.optionDisabled?b(e,this.optionDisabled):!1},onOptionSelect:function(e,n,r){var i=this;if(!(this.disabled||this.isOptionDisabled(n))){var o=this.isSelected(n);if(!(o&&!this.allowEmpty)){var a=this.getOptionValue(n),l;this.multiple?o?l=this.d_value.filter(function(u){return!p(u,a,i.equalityKey)}):l=this.d_value?[].concat(X(this.d_value),[a]):[a]:l=o?null:a,this.writeValue(l,e),this.$emit("change",{event:e,value:l})}}},isSelected:function(e){var n=!1,r=this.getOptionValue(e);if(this.multiple){if(this.d_value){var i=W(this.d_value),o;try{for(i.s();!(o=i.n()).done;){var a=o.value;if(p(a,r,this.equalityKey)){n=!0;break}}}catch(l){i.e(l)}finally{i.f()}}}else n=p(this.d_value,r,this.equalityKey);return n}},computed:{equalityKey:function(){return this.optionValue?null:this.dataKey}},directives:{ripple:$},components:{ToggleButton:B}},Z=["aria-labelledby"];function _(t,e,n,r,i,o){var a=I("ToggleButton");return g(),c("div",d({class:t.cx("root"),role:"group","aria-labelledby":t.ariaLabelledby},t.ptmi("root")),[(g(!0),c(z,null,C(t.options,function(l,u){return g(),K(a,{key:o.getOptionRenderKey(l),modelValue:o.isSelected(l),onLabel:o.getOptionLabel(l),offLabel:o.getOptionLabel(l),disabled:t.disabled||o.isOptionDisabled(l),unstyled:t.unstyled,size:t.size,readonly:!t.allowEmpty&&o.isSelected(l),onChange:function(O){return o.onOptionSelect(O,l,u)},pt:t.ptm("pcToggleButton")},D({_:2},[t.$slots.option?{name:"default",fn:x(function(){return[h(t.$slots,"option",{option:l,index:u},function(){return[f("span",d({ref_for:!0},t.ptm("pcToggleButton").label),S(o.getOptionLabel(l)),17)]})]}),key:"0"}:void 0]),1032,["modelValue","onLabel","offLabel","disabled","unstyled","size","readonly","onChange","pt"])}),128))],16,Z)}Q.render=_;export{B as a,Q as s};
