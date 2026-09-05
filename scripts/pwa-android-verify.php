<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local')) exit('Local only');
foreach (['offline','online'] as $mode) {
 $r=json_decode(file_get_contents(storage_path('app/pwa-android-test/'.$mode.'.json')),true);
 foreach($r['documents'] as $d) {
  $table=$d['doc_type']==='proforma'?'invoicing_sales_proformas':'invoicing_sales_invoices';
  $current=collect(json_decode(file_get_contents(storage_path('app/pwa-android-test/current-documents.json')),true))->firstWhere('local_uuid',$d['local_uuid']);
  $query=Illuminate\Support\Facades\DB::table($table)->where('tenant_id',36);
  $rows=$d['doc_type']==='proforma'?$query->where('id',$current['_server_id']??0)->get():$query->where('local_uuid',$d['local_uuid'])->get();
  foreach($rows as $row) echo json_encode(['mode'=>$mode,'type'=>$d['doc_type'],'id'=>$row->id,'number'=>$row->invoice_number??$row->proforma_number??null,'status'=>$row->status,'client_id'=>$row->client_id,'total'=>$row->total,'count'=>$rows->count()]).PHP_EOL;
 }
}
