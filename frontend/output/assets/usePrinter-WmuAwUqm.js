import{r as m,b1 as f,ab as h,ag as w}from"./index-CltWC9mn.js";const E="/assets/header-big-PYnGij3P.jpeg",b=(r,n)=>{Array.from(r.querySelectorAll('style, link[rel="stylesheet"]')).forEach(u=>{const o=u.cloneNode(!0);n.head.appendChild(o)})},v=r=>{const n=r.createElement("style");n.type="text/css",n.textContent=`
        html, body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            background: #fff;
        }

        @media print {
            html, body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    `,r.head.appendChild(n)},g=()=>{const r=m(!1),n=m(null),d=async o=>{const c=o.document;c.readyState!=="complete"&&await new Promise(a=>{const e=()=>{o.removeEventListener("load",e),a()};o.addEventListener("load",e,{once:!0})});const t=Array.from(c.images||[]);t.length&&await Promise.all(t.map(a=>new Promise(e=>{if(a.complete){e();return}const s=()=>{a.removeEventListener("load",s),a.removeEventListener("error",s),e()};a.addEventListener("load",s,{once:!0}),a.addEventListener("error",s,{once:!0})})))};return{printComponent:async(o,c={},t={})=>{r.value=!0,n.value=null,t=Object.assign({autoPrint:!0},t);const a=t.windowFeatures||"width=900,height=900,scrollbars=yes",e=window.open("","_blank",a);if(!e){n.value="Popup bloquée",r.value=!1;return}const s=t.title||"Impression";e.document.open(),e.document.write(`<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8" /><title>${s}</title></head><body></body></html>`),e.document.close(),b(document,e.document),v(e.document);const l=e.document.createElement("div");l.style.width=t.width||"100%",l.style.margin=t.margin||"0 auto",l.style.background="#fff",e.document.body.appendChild(l);const y=f({render:()=>w(o,c)});try{if(y.mount(l),await h(),e.focus(),t.autoPrint){const i=t.printDelay||250;await d(e),i>0&&await new Promise(p=>setTimeout(p,i)),e.print(),t.autoClose&&e.addEventListener("afterprint",()=>{e.close()},{once:!0})}}catch(i){n.value=i}finally{r.value=!1}},isPrinting:r,lastError:n}};export{E as h,g as u};
