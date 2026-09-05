import {chromium} from '@playwright/test';
import {execFileSync} from 'node:child_process';
import {writeFileSync,readFileSync} from 'node:fs';
import assert from 'node:assert/strict';
const adb=process.env.LOCALAPPDATA+'/Android/Sdk/platform-tools/adb.exe';
const out='storage/app/pwa-android-test';
const b=await chromium.connectOverCDP('http://127.0.0.1:9222');
const p=b.contexts()[0].pages().find(p=>p.url().includes('/offline/'));
const report=[];
try{
 await p.goto('http://localhost:8321/invoicing/offline/drafts',{waitUntil:'domcontentloaded'});
 await p.waitForFunction(()=>window.SosPwa?.pdfDe?.toString().includes('canonicalKey'));
 const ids=JSON.parse(readFileSync(out+'/offline.json')).documents.filter((d,i)=>i%2===1).map(d=>d.local_uuid);
 for(const online of [true,false]){
  if(!online){execFileSync(adb,['reverse','--remove','tcp:8321']);execFileSync(adb,['shell','svc','wifi','disable']);execFileSync(adb,['shell','svc','data','disable']);await p.reload({waitUntil:'domcontentloaded'});await p.waitForFunction(()=>window.SosPwa?.db?.isOpen());}
  for(const id of ids){
   const r=await p.evaluate(async(id)=>{
    const d=await window.SosPwa.db.draft_documents.get(id);
    const pdf=await window.SosPwa.pdfDe('documento',d);
    const bytes=new Uint8Array(await pdf.blob.arrayBuffer());
    const hash=Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',bytes))).map(v=>v.toString(16).padStart(2,'0')).join('');
    let opened=null;const old=window.open;window.open=()=>({document:{open(){},write(html){opened=html;},close(){}}});
    try{await window.SosPwa.imprimirDocumento(id);}finally{window.open=old;}
    const preview=opened?new TextEncoder().encode(opened):null;
    const previewHash=preview?Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',preview))).map(v=>v.toString(16).padStart(2,'0')).join(''):null;
    return {id,type:d.doc_type,origem:pdf.origem,bytes:Array.from(bytes),hash,previewHash,canShare:navigator.canShare?.({files:[new File([pdf.blob],'teste.pdf',{type:'application/pdf'})]})};
   },id);
   writeFileSync(out+'/'+r.type+'-'+(online?'online':'offline')+'.pdf',Buffer.from(r.bytes));delete r.bytes;
   assert.ok(r.previewHash);report.push({...r,online});console.log(JSON.stringify(report.at(-1)));
  }
 }
 for(const id of ids){const pair=report.filter(r=>r.id===id);assert.equal(pair[0].hash,pair[1].hash);assert.equal(pair[0].previewHash,pair[1].previewHash);}
}catch(e){report.push({error:e.stack});console.error(e);process.exitCode=1;}
finally{execFileSync(adb,['reverse','tcp:8321','tcp:8321']);execFileSync(adb,['shell','svc','wifi','enable']);execFileSync(adb,['shell','svc','data','enable']);writeFileSync(out+'/pdf-result.json',JSON.stringify(report,null,2));await b.close();}
