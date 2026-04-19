import{H as a,J as o,o as i,c as s,X as l}from"./index-kCrd55C3.js";var d=({dt:n})=>`
.p-skeleton {
    overflow: hidden;
    background: ${n("skeleton.background")};
    border-radius: ${n("skeleton.border.radius")};
}

.p-skeleton::after {
    content: "";
    animation: p-skeleton-animation 1.2s infinite;
    height: 100%;
    left: 0;
    position: absolute;
    right: 0;
    top: 0;
    transform: translateX(-100%);
    z-index: 1;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0), ${n("skeleton.animation.background")}, rgba(255, 255, 255, 0));
}

[dir='rtl'] .p-skeleton::after {
    animation-name: p-skeleton-animation-rtl;
}

.p-skeleton-circle {
    border-radius: 50%;
}

.p-skeleton-animation-none::after {
    animation: none;
}

@keyframes p-skeleton-animation {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(100%);
    }
}

@keyframes p-skeleton-animation-rtl {
    from {
        transform: translateX(100%);
    }
    to {
        transform: translateX(-100%);
    }
}
`,p={root:{position:"relative"}},c={root:function(t){var e=t.props;return["p-skeleton p-component",{"p-skeleton-circle":e.shape==="circle","p-skeleton-animation-none":e.animation==="none"}]}},u=a.extend({name:"skeleton",style:d,classes:c,inlineStyles:p}),m={name:"BaseSkeleton",extends:o,props:{shape:{type:String,default:"rectangle"},size:{type:String,default:null},width:{type:String,default:"100%"},height:{type:String,default:"1rem"},borderRadius:{type:String,default:null},animation:{type:String,default:"wave"}},style:u,provide:function(){return{$pcSkeleton:this,$parentInstance:this}}},h={name:"Skeleton",extends:m,inheritAttrs:!1,computed:{containerStyle:function(){return this.size?{width:this.size,height:this.size,borderRadius:this.borderRadius}:{width:this.width,height:this.height,borderRadius:this.borderRadius}}}};function f(n,t,e,k,g,r){return i(),s("div",l({class:n.cx("root"),style:[n.sx("root"),r.containerStyle],"aria-hidden":"true"},n.ptmi("root")),null,16)}h.render=f;export{h as s};
