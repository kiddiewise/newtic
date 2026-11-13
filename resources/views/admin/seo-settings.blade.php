@extends('admin.layout')
@section('title', 'SEO Ayarları | NEWTIC')
@section('topbar', 'SEO Yönetimi')
@section('content')
<div class="admin-page-heading">
  <div>
    <p class="admin-eyebrow">PAZARLAMA / ARAMA MOTORLARI</p>
    <h1>SEO Yönetimi</h1>
    <p>Ana sayfa, Google indeksleme, ürün ve kategori meta verileri ile sosyal medya görünümünü tek merkezden yönet.</p>
  </div>
</div>

<section class="admin-panel seo-overview">
  <div class="seo-overview-heading">
    <div>
      <p class="admin-eyebrow">BU ALAN ADININ SEO DURUMU</p>
      <h2>{{ $isStaging ? 'Test ortamı · Arama motorlarına kapalı' : ($setting->noindex ? 'Site indeksleme kapalı' : 'Genel indeksleme açık') }}</h2>
      <p><strong>Aktif alan adı:</strong> <code>{{ $activeOrigin }}</code></p>
    </div>
    <span class="seo-status-dot {{ $isStaging || $setting->noindex ? 'is-private' : 'is-public' }}">{{ $isStaging || $setting->noindex ? 'NOINDEX' : 'INDEX' }}</span>
  </div>
  @if($isStaging)
    <p class="seo-notice">Hostinger geçici alan adı, güvenlik nedeniyle <strong>zorunlu noindex/nofollow</strong> kullanır. Yönetim panelinde anahtarları kapatsan da test alan adı indekslemeye açılmaz. Yeni bir gerçek domaine geçildiğinde ayarlar otomatik olarak o alan adına uygulanır.</p>
  @else
    <p class="admin-description">Bu alan adı için ayarlar aktif. İndeksleme açık olsa bile Google'da görünme süresi ve uygunluk Google tarafından belirlenir.</p>
  @endif
</section>

<form method="POST" action="{{ route('admin.seo.update') }}" enctype="multipart/form-data" class="seo-admin-main">
  @csrf @method('PATCH')
  <section class="admin-panel" id="seo-general">
    <div class="admin-panel-title"><h2>1. Site geneli ve ana sayfa</h2></div>
    <div class="admin-form admin-settings-grid">
      <label>Mağaza / marka adı *
        <input name="site_name" maxlength="120" required value="{{ old('site_name', $setting->site_name) }}" placeholder="Polaris Kurşun">
        <small>Site genelinde marka ve Organization şeması için kullanılır.</small>
      </label>
      <label>Varsayılan sayfa başlığı son eki *
        <input name="title_suffix" maxlength="100" required value="{{ old('title_suffix', $setting->title_suffix) }}" placeholder="Polaris Kurşun">
        <small>Özel SEO başlığı yoksa kategori ve ürün adından sonra kullanılır.</small>
      </label>
      <label class="admin-full">Ana sayfa SEO başlığı
        <input name="home_title" maxlength="190" value="{{ old('home_title', $setting->home_title) }}" placeholder="Polaris Kurşun | Balıkçılık ve Olta Kurşunları">
        <small>Öneri: doğal ve açıklayıcı bir başlık; karakter sayısı tek başına sıralama kriteri değildir.</small>
      </label>
      <label class="admin-full">Ana sayfa meta açıklaması
        <textarea name="home_description" rows="3" maxlength="500" placeholder="Markayı ve ürünleri benzersiz biçimde anlatın.">{{ old('home_description', $setting->home_description) }}</textarea>
      </label>
      <label class="admin-full">Diğer sayfaların varsayılan açıklaması
        <textarea name="default_description" rows="3" maxlength="500" placeholder="Mağazanın sunduğu ürünleri tanıtan kısa açıklama">{{ old('default_description', $setting->default_description) }}</textarea>
      </label>
    </div>
  </section>

  <section class="admin-panel" id="seo-indexing">
    <div class="admin-panel-title"><h2>2. İndeksleme ve arama motorları</h2></div>
    <p class="admin-description">Bu anahtarlar gerçek mağaza domaininde etkilidir. Geliştirme alan adı her durumda kapalı kalır. Ödeme, sepet, hesap ve arama sayfaları ayrıca indekslemeye kapalı tutulur.</p>
    <div class="seo-robots-switches">
      <label class="seo-switch">
        <input type="hidden" name="noindex" value="0">
        <input type="checkbox" name="noindex" value="1" @checked(old('noindex', $setting->noindex))>
        <span><strong>Noindex — Arama sonuçlarında görünmeyi engelle</strong><small>Açıkken ürünler, kategoriler ve ana sayfanın indekslenmemesi istenir.</small></span>
      </label>
      <label class="seo-switch">
        <input type="hidden" name="nofollow" value="0">
        <input type="checkbox" name="nofollow" value="1" @checked(old('nofollow', $setting->nofollow))>
        <span><strong>Nofollow — Sayfadaki bağlantıları takip etmeme talimatı</strong><small>Genellikle kapalı bırakılır. Arama motorlarına sayfadaki bağlantıları izlememe talimatı verir.</small></span>
      </label>
    </div>
    <p class="seo-notice">Önemli: <code>robots.txt</code> engellemesiyle <code>noindex</code> aynı şey değildir. İndekslenebilir alan adında sayfalar taranabiliyorsa Google noindex talimatını okuyabilir. Gizli içerikler için ayrıca oturum açma koruması gerekir.</p>
    <div class="seo-link-grid">
      <a class="admin-secondary" href="{{ url('/robots.txt') }}" target="_blank" rel="noopener noreferrer">robots.txt</a>
      <a class="admin-secondary" href="{{ url('/sitemap.xml') }}" target="_blank" rel="noopener noreferrer">XML Site Haritası</a>
    </div>
    <p class="admin-description">Sitemap'e yalnızca görünür, yayındaki, indekslemeye izin verilen ürün ve kategoriler eklenir. 404 dönen test sitemap'i Google'a gönderilmemelidir.</p>
  </section>

  <section class="admin-panel" id="seo-social">
    <div class="admin-panel-title"><h2>3. Google Search Console ve paylaşım görseli</h2></div>
    <div class="admin-form admin-settings-grid">
      <label class="admin-full">Google Search Console HTML doğrulama kodu
        <input name="google_verification" maxlength="190" autocomplete="off" value="{{ old('google_verification', $setting->google_verification) }}" placeholder="Yalnızca content alanındaki doğrulama dizgesi">
        <small>HTML meta etiketi içindeki <code>content="..."</code> değerini yaz. Alan adı değişince yeni domaini Search Console'da ayrıca doğrula.</small>
      </label>
      <label class="admin-full">Varsayılan sosyal medya paylaşım görseli
        <input type="file" name="social_image" accept="image/jpeg,image/png,image/webp,image/avif">
        <small>Önerilen boyut 1200 × 630. JPG, PNG, WebP veya AVIF, en fazla 4 MB. Ürünün kendi fotoğrafı varsa öncelik onundur.</small>
      </label>
      @if($setting->social_image_path)
        <div class="admin-full seo-social-preview">
          <img src="{{ asset($setting->social_image_path) }}" width="360" height="190" alt="Mevcut sosyal paylaşım görseli">
          <label class="admin-check">
            <input type="checkbox" name="remove_social_image" value="1">
            Mevcut genel görseli kaldır
          </label>
        </div>
      @endif
    </div>
  </section>

  <section class="admin-panel" id="seo-pages">
    <div class="admin-panel-title"><h2>4. Diğer sayfaların meta bilgileri</h2></div>
    <p class="admin-description">Sepet, hesap, ödeme ve arama gibi yardımcı sayfaların başlık ve açıklamalarını düzenleyebilirsin. Bu sayfalar güvenlik ve gereksiz arama sonuçlarını önlemek için <strong>noindex</strong> kalır.</p>
    <div class="seo-page-grid">
      @foreach($pages as $key => $label)
        @php $pageValue = (array) (($setting->page_metadata ?? [])[$key] ?? []); @endphp
        <div class="seo-page-item">
          <strong>{{ $label }}</strong>
          <label>Sayfa başlığı
            <input maxlength="190" name="pages[{{ $key }}][title]" value="{{ old('pages.'.$key.'.title', $pageValue['title'] ?? '') }}" placeholder="{{ $label }} | Polaris Kurşun">
          </label>
          <label>Meta açıklaması
            <textarea rows="2" maxlength="500" name="pages[{{ $key }}][description]" placeholder="İsteğe bağlı açıklama">{{ old('pages.'.$key.'.description', $pageValue['description'] ?? '') }}</textarea>
          </label>
        </div>
      @endforeach
    </div>
  </section>

  <div class="seo-save-bar">
    <p>Değişiklikler otomatik olarak mevcut site alan adına uygulanır. Geliştirme domaininde noindex korunur.</p>
    <button type="submit" class="admin-primary">SEO ayarlarını kaydet</button>
  </div>
</form>

<section class="admin-panel" id="seo-audit">
  <div class="admin-panel-title"><h2>5. Katalog SEO kontrolleri</h2></div>
  <div class="seo-audit-grid">
    <div><strong>{{ $stats['products'] }}</strong><span>Yayındaki gerçek ürün</span></div>
    <div><strong>{{ $stats['product_no_image'] }}</strong><span>Görseli eksik ürün</span></div>
    <div><strong>{{ $stats['product_no_description'] }}</strong><span>Açıklaması eksik ürün</span></div>
    <div><strong>{{ $stats['product_no_title'] }}</strong><span>Özel SEO başlığı olmayan ürün</span></div>
    <div><strong>{{ $stats['categories'] }}</strong><span>Yayındaki kategori</span></div>
    <div><strong>{{ $stats['category_no_title'] }}</strong><span>Özel SEO başlığı olmayan kategori</span></div>
  </div>
  <p class="admin-description">Özel SEO başlığı olmaması tek başına hata değildir; sistem ürün/kategori adından otomatik başlık oluşturur. Benzersiz metin ve kaliteli fotoğraflar daha önemlidir.</p>
  <div class="seo-link-grid">
    <a class="admin-secondary" href="{{ route('admin.products.index') }}">Ürün SEO alanlarını düzenle</a>
    <a class="admin-secondary" href="{{ route('admin.categories.index') }}">Kategori SEO alanlarını düzenle</a>
    <a class="admin-secondary" href="{{ route('admin.google-merchant.edit') }}">Google Merchant Center</a>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel-title"><h2>6. Google'a çıkmadan önce</h2></div>
  <p class="admin-description">Ürün fotoğrafı, fiyatı, stok durumu ve yıldızı için Google'a doğru <code>Product</code>/<code>Offer</code> JSON-LD verileri sunulur. Yıldızlar yalnızca gerçek, onaylanmış, test dışı satın alma yorumlarından gelir. Google, rich result gösterimini garanti etmez.</p>
  <p class="admin-description">Google Shopping için ayrıca Merchant Center bağlantısı, doğrulanmış gerçek domain, doğru kargo/iade bilgileri ve Google politikalarına uygun ürünler gerekir. Staging mağazasından ürün gönderme; gerçek domain yayına geçince Merchant Center ve Search Console'da doğrula.</p>
  <p class="admin-description">Eski WordPress adresleri değişecekse <strong>301 yönlendirme tablosu</strong> oluşturmak gerekir. Canonical/sitemap tek başına eski bağlantıların aktarılmasını sağlamaz.</p>
  <div class="seo-link-grid">
    <a class="admin-secondary" href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer">Google Search Console</a>
    <a class="admin-secondary" href="https://search.google.com/test/rich-results" target="_blank" rel="noopener noreferrer">Google Zengin Sonuçlar Testi</a>
    <a class="admin-secondary" href="https://merchants.google.com/" target="_blank" rel="noopener noreferrer">Merchant Center</a>
  </div>
</section>
@endsection