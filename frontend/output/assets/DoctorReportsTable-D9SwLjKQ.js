import{r as R,l as w,o as p,c as b,e as l,w as c,b as s,t as i,j as f,f as d,m as y,d as v,s as $,F as C,g as E}from"./index-kCrd55C3.js";import{s as I}from"./index-B9iCSAAt.js";import{s as B,a as m}from"./index-DJpzmDnv.js";import{s as F}from"./index-ByCS5jRW.js";import{s as P}from"./index-OtLiqqlY.js";const q={class:"flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-surface-200/60 pb-3 dark:border-surface-700/60"},z={class:"text-base sm:text-lg font-semibold text-surface-900 dark:text-surface-0"},j={key:0,class:"text-xs sm:text-sm text-surface-500 dark:text-surface-400"},O={key:0,class:"grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-6"},V={class:"rounded-2xl border border-surface-200/60 bg-surface-50/80 p-3 sm:p-4 text-center shadow-sm dark:border-surface-700/60 dark:bg-surface-800/70"},K={class:"text-lg sm:text-xl font-semibold text-surface-900 dark:text-surface-0"},W={class:"rounded-2xl border border-surface-200/60 bg-surface-50/80 p-3 sm:p-4 text-center shadow-sm dark:border-surface-700/60 dark:bg-surface-800/70"},U={class:"text-lg sm:text-xl font-semibold text-surface-900 dark:text-surface-0"},G={class:"rounded-2xl border border-surface-200/60 bg-surface-50/80 p-3 sm:p-4 text-center shadow-sm dark:border-surface-700/60 dark:bg-surface-800/70"},H={class:"text-lg sm:text-xl font-semibold text-surface-900 dark:text-surface-0"},J={class:"overflow-x-auto"},Q={class:"text-[11px] sm:text-xs text-surface-500"},X={class:"rounded-2xl border border-surface-200/60 bg-surface-50/80 p-3 text-xs sm:text-sm shadow-sm dark:border-surface-700/60 dark:bg-surface-800/70"},Y={key:0,class:"text-surface-500"},Z={key:1},tt={class:"w-full border-collapse text-xs sm:text-sm"},et={class:"border-b p-2"},at={class:"border-b p-2"},st={class:"border-b p-2"},rt={class:"border-b p-2"},ot={class:"flex flex-col gap-2"},pt={__name:"DoctorReportsTable",props:{title:{type:String,default:"Rapports périodiques par médecin"},periodLabel:{type:String,default:""},data:{type:Object,default:()=>({kpi:{},doctors:[]})},loading:{type:Boolean,default:!1},showKpi:{type:Boolean,default:!1},variant:{type:String,default:"admin"}},setup(u){const x=u,A=R(null),g=R(!1),k=w(()=>x.data?.doctors||[]),_=w(()=>x.data?.kpi||{});function r(a){const e=Number(a||0);return`${new Intl.NumberFormat("fr-FR").format(e)} Fcfa`}function L(a){const e=Array.isArray(a?.paiements_period)?a.paiements_period:[];if(!e.length)return'<div class="p-3"><em>Aucune entrée enregistrée sur cette période.</em></div>';let t=0;return`
        <div class="p-3">
            <table style="width:100%; border-collapse: collapse;" border="1" cellpadding="6">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Description</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    ${e.map(o=>(t+=Number(o.montant_paye||0),`
                <tr>
                    <td>${o.date||"--"}</td>
                    <td>${o.patient||"--"}</td>
                    <td>${o.description||"--"}</td>
                    <td>${r(o.montant_paye)}</td>
                </tr>
            `)).join("")}
                </tbody>
            </table>
            <p style="margin-top: 12px; font-weight: 600;">Total = ${r(t)}</p>
        </div>
    `}function D(a){const e=window.open("","_blank");e&&(e.document.write(a),e.document.close(),setTimeout(()=>{e.focus(),e.print(),e.close()},500))}function M(a){const e=new Date().toLocaleDateString("fr-FR"),t=`
        <html>
        <head>
            <title>Rapport Dr ${a.name||""}</title>
            <style>
                @page { size: A4 landscape; margin: 10mm; }
                body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.4; color: #000; margin: 0; padding: 10mm; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
                .header h2 { margin: 0; font-size: 18pt; text-transform: uppercase; }
                .header p { margin: 5px 0 0; font-size: 11pt; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .section-title { font-weight: bold; font-size: 13pt; margin: 25px 0 10px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
                .signature-table { margin-top: 40px; width: 100%; }
                .signature-cell { width: 45%; text-align: center; }
                .signature-line { border-top: 1px solid #000; width: 80%; margin: 15px auto 5px; }
                .footer { font-size: 9pt; text-align: center; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>CABINET DENTAIRE ORODENT
</h2>
                <p>RAPPORT DE SERVICE MÉDICAL</p>
                <p>Rue 612 Bacodjicoroni ACI | Bamako-MALI | Tél: +223 77 27 28 61 / +223 44 51 61 85</p>
            </div>
            <div>
                <strong>Médecin :</strong> Dr ${a.name||""}<br />
                <strong>Période concernée :</strong> ${x.periodLabel||"(non spécifiée)"}
            </div>
            <div class="section-title">Statistiques d'activité</div>
            ${N(a)}
            <div class="section-title">Détails des soins effectués</div>
            ${L(a)}
            <table class="signature-table">
                <tr>
                    <td class="signature-cell">
                        <div class="signature-line"></div>
                        <p>Signature du praticien</p>
                    </td>
                    <td class="signature-cell">
                        <div class="signature-line"></div>
                        <p>Cachet et visa de la direction</p>
                    </td>
                </tr>
            </table>
            <div class="footer">Document généré automatiquement le ${e}</div>
        </body>
        </html>
    `;D(t)}function N(a){return`
        <table>
            <thead>
                <tr>
                    <th>Indicateur</th>
                    <th>Nombre</th>
                    <th>Montant total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Consultations réalisées</td>
                    <td>${a.consultations||0}</td>
                    <td>${a.consultations_amount?r(a.consultations_amount):r(0)}</td>
                </tr>
                <tr>
                    <td>Actes posés</td>
                    <td>${a.acts||0}</td>
                    <td>${a.acts_amount?r(a.acts_amount):r(0)}</td>
                </tr>
                <tr>
                    <td colspan="2">Apport total</td>
                    <td>${a.apport?r(a.apport):r(0)}</td>
                </tr>
                <tr>
                    <td colspan="2">Montant payé</td>
                    <td>${a.revenue?r(a.revenue):r(0)}</td>
                </tr>
                <tr>
                    <td colspan="2">Reliquat patients</td>
                    <td>${a.reliquat?r(a.reliquat):r(0)}</td>
                </tr>
            </tbody>
        </table>
    `}function T(){const a=new Date().toLocaleDateString("fr-FR"),e=k.value.map(n=>`
            <tr>
                <td>${n.name||""}</td>
                <td>${n.consultations||0} (${n.consultations_paid||0} payantes)</td>
                <td>${r(n.apport)}</td>
                <td>${r(n.revenue)}</td>
                <td>${r(n.reliquat)}</td>
                <td>${r(n.salary)}</td>
            </tr>
        `).join(""),t=`
        <html>
        <head>
            <title>Rapport de service</title>
            <style>
                @page { size: A4 landscape; margin: 20mm; }
                body { font-family: Arial, sans-serif; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background: #f2f2f2; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>CABINET DENTAIRE ORODENT
</h2>
                <p><strong>Rapport de service (Résumé)</strong> - ${a}</p>
                <p>Rue 612 Bacodjicoroni ACI | Bamako-MALI | Tél: +223 77 27 28 61 / +223 44 51 61 85</p>
            </div>
            <p><strong>Période :</strong> ${x.periodLabel||"(non spécifiée)"}</p>
            <table>
                <thead>
                    <tr>
                        <th>Médecin</th>
                        <th>Consultations</th>
                        <th>Montant généré (Fcfa)</th>
                        <th>Montant payé (Fcfa)</th>
                        <th>Réliquat patient</th>
                        <th>Salaire</th>
                    </tr>
                </thead>
                <tbody>
                    ${e}
                </tbody>
            </table>
        </body>
        </html>
    `;g.value=!1,D(t)}function S(){const a=[];k.value.forEach(o=>{Array.isArray(o.paiements_period)&&o.paiements_period.forEach(h=>{a.push({date:h.date||"--",medecin:o.name||"--",patient:h.patient||"--",description:h.description||"--",montant:h.montant_paye||0})})});const e=a.map(o=>`
            <tr>
                <td>${o.date}</td>
                <td>${o.medecin}</td>
                <td>${o.patient}</td>
                <td>${o.description}</td>
                <td>${r(o.montant)}</td>
            </tr>
        `).join(""),t=a.reduce((o,h)=>o+Number(h.montant||0),0),n=`
        <html>
        <head>
            <title>Liste des actes médicaux</title>
            <style>
                @page { size: A4 landscape; margin: 20mm; }
                body { font-family: Arial, sans-serif; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background: #f2f2f2; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Liste des actes médicaux</h2>
                <p><strong>Période :</strong> ${x.periodLabel||"(non spécifiée)"}</p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Médecin</th>
                        <th>Patient</th>
                        <th>Description</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    ${e}
                </tbody>
            </table>
            <p style="margin-top: 12px; font-weight: 600;">Total = ${r(t)}</p>
        </body>
        </html>
    `;g.value=!1,D(n)}return(a,e)=>(p(),b(C,null,[l(d(I),{class:"rounded-2xl border border-surface-200/60 bg-gradient-to-b from-surface-0 via-surface-0 to-surface-50/70 shadow-sm dark:border-surface-700 dark:from-surface-900 dark:to-surface-800"},{title:c(()=>[s("div",q,[s("div",null,[s("h3",z,i(u.title),1),u.periodLabel?(p(),b("p",j,"Période : "+i(u.periodLabel),1)):f("",!0)]),l(d($),{label:"Imprimer",icon:"pi pi-print",outlined:"",class:"w-full sm:w-auto",onClick:e[0]||(e[0]=t=>g.value=!0)})])]),content:c(()=>[u.showKpi?(p(),b("div",O,[s("div",V,[e[3]||(e[3]=s("p",{class:"text-xs font-semibold uppercase text-surface-500"},"Apports total",-1)),s("p",K,i(r(_.value.totalRevenue)),1)]),s("div",W,[e[4]||(e[4]=s("p",{class:"text-xs font-semibold uppercase text-surface-500"},"Après retrait des %",-1)),s("p",U,i(r(_.value.afterFees)),1)]),s("div",G,[e[5]||(e[5]=s("p",{class:"text-xs font-semibold uppercase text-surface-500"},"Salaires totaux",-1)),s("p",H,i(r(_.value.totalSalaries)),1)])])):f("",!0),s("div",J,[l(d(B),{value:k.value,dataKey:"name",responsiveLayout:"scroll",loading:u.loading,expandedRows:A.value,"onUpdate:expandedRows":e[1]||(e[1]=t=>A.value=t),paginator:"",rows:8,class:"min-w-[640px] text-xs sm:text-sm rounded-xl overflow-hidden border border-surface-200/60 dark:border-surface-700/60"},{expansion:c(({data:t})=>[s("div",X,[!t?.paiements_period||!t.paiements_period.length?(p(),b("div",Y," Aucune entrée enregistrée sur cette période. ")):(p(),b("div",Z,[s("table",tt,[e[6]||(e[6]=s("thead",null,[s("tr",{class:"text-left text-surface-500"},[s("th",{class:"border-b p-2"},"Date"),s("th",{class:"border-b p-2"},"Patient"),s("th",{class:"border-b p-2"},"Description"),s("th",{class:"border-b p-2"},"Montant")])],-1)),s("tbody",null,[(p(!0),b(C,null,E(t.paiements_period,(n,o)=>(p(),b("tr",{key:o},[s("td",et,i(n.date),1),s("td",at,i(n.patient),1),s("td",st,i(n.description),1),s("td",rt,i(r(n.montant_paye)),1)]))),128))])])]))])]),default:c(()=>[l(d(m),{expander:"",style:{width:"2.5rem"}}),l(d(m),{field:"name",header:"Médecin"}),l(d(m),{header:"Consultations"},{body:c(({data:t})=>[s("span",null,i(t.consultations||0),1),s("span",Q," ("+i(t.consultations_paid||0)+" payantes)",1)]),_:1}),u.variant==="admin"?(p(),y(d(m),{key:0,header:"Montant généré"},{body:c(({data:t})=>[v(i(r(t.apport)),1)]),_:1})):f("",!0),u.variant==="admin"?(p(),y(d(m),{key:1,header:"Montant payé"},{body:c(({data:t})=>[v(i(r(t.revenue)),1)]),_:1})):f("",!0),u.variant==="admin"?(p(),y(d(m),{key:2,header:"Reliquat patient"},{body:c(({data:t})=>[v(i(r(t.reliquat)),1)]),_:1})):f("",!0),u.variant==="reception"?(p(),y(d(m),{key:3,header:"Montant"},{body:c(({data:t})=>[v(i(r(t.apport)),1)]),_:1})):f("",!0),u.variant==="reception"?(p(),y(d(m),{key:4,header:"Payantes / Gratuites"},{body:c(({data:t})=>[l(d(P),{value:`${t.consultations_paid||0} / ${(t.consultations||0)-(t.consultations_paid||0)}`,severity:"info"},null,8,["value"])]),_:1})):f("",!0),l(d(m),{header:"Salaire"},{body:c(({data:t})=>[v(i(r(t.salary)),1)]),_:1}),l(d(m),{header:"Action",style:{width:"6rem"}},{body:c(({data:t})=>[l(d($),{icon:"pi pi-print",text:"",rounded:"",onClick:n=>M(t)},null,8,["onClick"])]),_:1})]),_:1},8,["value","loading","expandedRows"])])]),_:1}),l(d(F),{visible:g.value,"onUpdate:visible":e[2]||(e[2]=t=>g.value=t),modal:"",header:"Choix d'impression",style:{width:"90vw",maxWidth:"32rem"}},{default:c(()=>[e[7]||(e[7]=s("p",{class:"mb-4"},"Souhaitez-vous imprimer :",-1)),s("div",ot,[l(d($),{label:"Liste des médecins (résumé)",icon:"pi pi-print",onClick:T}),l(d($),{label:"Liste des soins détaillée",icon:"pi pi-file",severity:"secondary",onClick:S})])]),_:1},8,["visible"])],64))}};export{pt as _};
