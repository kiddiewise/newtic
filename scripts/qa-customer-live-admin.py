#!/usr/bin/env python3
"""Limited LIVE management QA: unique temporary records only; no payments/orders.

Tests actual CSRF-protected HTML endpoints with a short-lived QA administrator.
Removes the QA administrator, QA product/gallery, and QA category on completion.
Never modifies pre-existing products or categories or other website databases.
"""
import base64
import json
import os
import re
import secrets
import shlex
import subprocess
import sys
from urllib.parse import urlparse

import requests

URL="https://humane-indigo-chough-50.hostingersite.com"
ROOT="/home/u880685189/domains/humane-indigo-chough-50.hostingersite.com/newtic"
SSH=["ssh","-p","65002","-i",os.path.expanduser("~/.ssh/newtic-qa"),
     "-o","IdentitiesOnly=yes","-o","StrictHostKeyChecking=yes",
     "-o","BatchMode=yes","-o","ConnectTimeout=12","u880685189@147.79.123.121"]
ID=secrets.token_hex(5)
EMAIL=f"newtic.qa.{ID}@example.invalid"
PASSWORD=secrets.token_urlsafe(40)+"Q9!"
CATEGORY=f"NEWTIC-QA-CATEGORY-{ID}"
PRODUCT=f"NEWTIC-QA-PRODUCT-{ID}"
SKU=f"QA-{ID.upper()}"
errors=[]
category_id=None
product_id=None
created_admin=False
session=requests.Session()
session.headers.update({"User-Agent":"NEWTIC-Customer-Authorized-QA/1.0"})

BOOTSTRAP=r"""
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('app.url') !== 'https://humane-indigo-chough-50.hostingersite.com'
  || \Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'u880685189_newtic_test01'
  || !config('newtic.checkout_test_mode', false)
  || config('newtic.paytr_card_payments_enabled', false)
  || config('newtic.paytr_webhooks_enabled', false)
  || config('newtic.shipping_dummy_enabled', false)
  || config('newtic.marketing_live_enabled', false)) {
  fwrite(STDERR, "FAIL: unauthorized target or unsafe payment/shipping mode\n");
  exit(2);
}
"""
def remote_php(body, payload=None, *, timeout=40):
    command="cd "+shlex.quote(ROOT)+" && php -r "+shlex.quote(BOOTSTRAP+body)
    cp=subprocess.run(SSH+[command],input=(json.dumps(payload) if payload is not None else None),
                      text=True,capture_output=True,timeout=timeout)
    if cp.returncode:
        raise RuntimeError("SSH Laravel QA operation failed: "+(cp.stderr or cp.stdout)[-400:])
    return cp.stdout.strip()
def report(ok,label,detail=""):
    prefix="PASS" if ok else "FAIL"
    suffix=f" ({detail})" if detail else ""
    print(f"{prefix} | {label}{suffix}",flush=True)
    if not ok: errors.append(label)
    return ok
def fetch(path, *, expected=200):
    r=session.get(URL+path,timeout=20,allow_redirects=False)
    report(r.status_code==expected,"GET "+path,"HTTP "+str(r.status_code))
    return r
def token(html):
    m=re.search(r'<input\b[^>]*\bname=["\']_token["\'][^>]*\bvalue=["\']([^"\']+)',html,re.I)
    if not m:
        m=re.search(r'<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)',html,re.I)
    if not m: raise RuntimeError("CSRF token is missing in authenticated form")
    return m.group(1)
def post(method,path,data,files=None):
    extra={"_token":token(fetch("/admin/categories").text)}
    extra.update(data)
    resp=session.request(method,URL+path,data=extra,files=files,allow_redirects=False,timeout=25)
    report(resp.status_code==302,method+" "+path,"HTTP "+str(resp.status_code))
    if resp.status_code!=302:
        raise RuntimeError("Unexpected nonredirect status on protected form")
    return resp
def extract_id(response,path_part):
    m=re.search(r"/admin/"+path_part+r"/(\d+)/edit",response.headers.get("Location",""))
    if not m: raise RuntimeError("Expected new edit URL absent from Location header")
    return int(m.group(1))
# Valid 1x1 PNG; upload rules require image MIME and a nonempty safe file.
PNG=base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l+0AAAAASUVORK5CYII=")
try:
    # Account is created only AFTER hostname/schema/test-mode assertions.
    remote_php(r"""
$input=json_decode(stream_get_contents(STDIN),true);
if (!is_array($input)) exit(2);
if (\App\Models\User::where('email',$input['email'])->exists()) exit(3);
$user=new \App\Models\User();
$user->forceFill(['name'=>'NEWTIC QA Temporary','email'=>$input['email'],
    'password'=>$input['password'],'is_admin'=>true,'email_verified_at'=>now()])->save();
echo "QA_ADMIN_CREATED\n";
""",{"email":EMAIL,"password":PASSWORD})
    created_admin=True
    report(True,"Temporary QA administrator provisioned in isolated test DB")
    login=fetch("/admin/login")
    login_token=token(login.text)
    resp=session.post(URL+"/admin/login",data={"_token":login_token,"email":EMAIL,"password":PASSWORD},
        allow_redirects=False,timeout=20)
    if not report(resp.status_code==302,"Authenticated admin login","HTTP "+str(resp.status_code)):
        raise RuntimeError("Cannot log in with one-time QA account")
    dashboard=fetch("/admin/dashboard")
    if dashboard.status_code!=200: raise RuntimeError("Admin dashboard unavailable")
    menu_pages=[
        "/admin/categories","/admin/products","/admin/orders","/admin/stock",
        "/admin/customers","/admin/reports","/admin/analitik-merkezi",
        "/admin/seo-ayarlari","/admin/yasal-sayfalar","/admin/blog",
        "/admin/google-giris","/admin/kampanya-eposta",
        "/admin/eposta-bildirimler","/admin/sistem-sagligi"
    ]
    for path in menu_pages: fetch(path)
    # Create category via actual admin POST and verify persisted database row.
    cat=post("POST","/admin/categories",
             {"name":CATEGORY,"description":"Automated QA fixture - safe to remove",
              "sort_order":"90000","is_active":"1"},
             files={"image":("qa-category.png",PNG,"image/png")})
    category_id=extract_id(cat,"categories")
    verify=remote_php(r"""
$x=\App\Models\Category::where('name',json_decode(stream_get_contents(STDIN),true)['name'])->first();
if (!$x || !$x->is_active || !$x->image_path) exit(3);
echo "CATEGORY_ID=".$x->id." IMAGE=".$x->image_path."\n";
""",{"name":CATEGORY})
    report("CATEGORY_ID="+str(category_id) in verify,"Category created and image persisted")
    patch=post("PATCH",f"/admin/categories/{category_id}",
             {"name":CATEGORY+"-UPDATED","sort_order":"90001","is_active":"1"})
    report(True,"Category update request accepted")
    # Create a sellable product with uploaded gallery and own SKU.
    product=post("POST","/admin/products",
             {"name":PRODUCT,"sku":SKU,"primary_category_id":str(category_id),
              "description":"QA AUTOTEST: not a real item","regular_price":"250.00",
              "sale_price":"199.90","stock_quantity":"9","is_active":"1",
              "tracks_stock":"1"},
             files={"images[0]":("qa-product.png",PNG,"image/png")})
    product_id=extract_id(product,"products")
    info=remote_php(r"""
$x=\App\Models\Product::where('sku',json_decode(stream_get_contents(STDIN),true)['sku'])->first();
if (!$x) exit(3);
echo json_encode(['id'=>$x->id,'price'=>$x->price,'stock'=>$x->stock_quantity,
  'images'=>$x->images()->count(),'slug'=>$x->slug,'active'=>$x->is_active]);
""",{"sku":SKU})
    fields=json.loads(info)
    report(fields["id"]==product_id and float(fields["price"])==199.9
           and int(fields["stock"])==9 and fields["images"]==1 and fields["active"],
           "Product created with sale price, stock, uploaded gallery")
    product_public=fetch("/urun/"+fields["slug"])
    report(PRODUCT.lower() in product_public.text.lower(),"New product visible on public detail")
    category_url=fetch("/kategori/"+re.sub(r"/.*","",re.search(r"/kategori/[^\" ]+",product_public.text).group(0))[9:]) if False else None
    changed=post("PATCH",f"/admin/products/{product_id}",
         {"name":PRODUCT+"-UPDATED","sku":SKU,"primary_category_id":str(category_id),
          "description":"QA updated description","regular_price":"275.00",
          "sale_price":"225.00","stock_quantity":"7","is_active":"1","tracks_stock":"1"})
    report(True,"Product update accepted")
    snapshot=json.loads(remote_php(r"""
$x=\App\Models\Product::where('sku',json_decode(stream_get_contents(STDIN),true)['sku'])->firstOrFail();
echo json_encode(['price'=>$x->price,'stock'=>$x->stock_quantity,'name'=>$x->name]);
""",{"sku":SKU}))
    report(float(snapshot["price"])==225.0 and int(snapshot["stock"])==7 and snapshot["name"]==PRODUCT+"-UPDATED",
           "Product update committed to live test database")
    # Deleting a category that has products must be rejected by the controller.
    block=post("DELETE",f"/admin/categories/{category_id}",{})
    still=remote_php(r"""
echo \App\Models\Category::where('id',json_decode(stream_get_contents(STDIN),true)['id'])->exists()?'KEPT':'DELETED';
""",{"id":category_id})
    report(still=="KEPT","Category deletion blocked when it has an associated product")
    # An unsold product may be deleted, then its now-empty category may be deleted.
    post("DELETE",f"/admin/products/{product_id}",{})
    prod_exists=remote_php(r"""
echo \App\Models\Product::where('sku',json_decode(stream_get_contents(STDIN),true)['sku'])->exists()?'YES':'NO';
""",{"sku":SKU})
    report(prod_exists=="NO","Unsold product deleted via authenticated admin DELETE")
    product_id=None
    post("DELETE",f"/admin/categories/{category_id}",{})
    cat_exists=remote_php(r"""
echo \App\Models\Category::where('id',json_decode(stream_get_contents(STDIN),true)['id'])->exists()?'YES':'NO';
""",{"id":category_id})
    report(cat_exists=="NO","Empty test category deleted via authenticated admin DELETE")
    category_id=None
except Exception as e:
    report(False,"E2E exception",str(e)[:240])
finally:
    try:
        # Defensive cleanup, restricted to unique exact QA keys, never
        # touching pre-existing customer catalog items or order-linked SKUs.
        out=remote_php(r"""
$x=json_decode(stream_get_contents(STDIN),true);
$p=\App\Models\Product::where('sku',$x['sku'])->first();
if($p){
 if(\Illuminate\Support\Facades\DB::table('order_items')->where('product_id',$p->id)->exists())exit(3);
 $paths=$p->images()->pluck('path')->push($p->image_path)->filter()->unique()->all();
 $p->delete();
 foreach($paths as $path) if(str_starts_with($path,'storage/products/'))
   \Illuminate\Support\Facades\Storage::disk('public')->delete(substr($path,8));
}
$c=\App\Models\Category::where('name','like',$x['category'].'%')->first();
if($c && !$c->products()->exists() && !$c->children()->exists()){
 $im=$c->image_path;$c->delete();
 if($im && str_starts_with($im,'storage/categories/'))
   \Illuminate\Support\Facades\Storage::disk('public')->delete(substr($im,8));
}
$u=\App\Models\User::where('email',$x['email'])->first();
if($u && $u->name==='NEWTIC QA Temporary' && $u->is_admin)$u->delete();
echo "QA_CLEANUP_OK\n";
""",{"email":EMAIL,"category":CATEGORY,"sku":SKU})
        report("QA_CLEANUP_OK" in out,"QA data and temporary admin cleanup complete")
    except Exception as e:
        report(False,"Cleanup exception",str(e)[:240])
print(f"SUMMARY: checks_failed={len(errors)}",flush=True)
sys.exit(1 if errors else 0)
