<aside class="newtic-cookie-banner" data-cookie-banner hidden aria-label="Çerez tercihleri">
  <div class="container newtic-cookie-inner">
    <div>
      <strong>Gizliliğiniz bizim için önemli.</strong>
      <p>Mağazanın çalışması için zorunlu çerezler kullanılır. Analitik ve pazarlama çerezleri ancak tercihinizle etkinleştirilebilir.
      @if(isset($publishedLegalPages['cerez-politikasi']))
        <a href="{{ route('legal.show', 'cerez-politikasi') }}">Çerez Politikası</a>
      @endif
      </p>
    </div>
    <div class="newtic-cookie-actions">
      <button type="button" data-cookie-decline>Reddet</button>
      <button type="button" data-cookie-preferences>Tercihler</button>
      <button type="button" data-cookie-accept>Tümünü kabul et</button>
    </div>
  </div>
</aside>
<dialog class="newtic-cookie-dialog" data-cookie-dialog aria-label="Çerez tercihlerinizi yönetin">
  <div class="newtic-cookie-dialog-content">
    <h2>Çerez tercihleri</h2>
    <p>Zorunlu çerezler temel işlemler için gereklidir. Diğer kategoriler varsayılan olarak kapalıdır; izin vermeseniz de alışveriş yapabilirsiniz.</p>
    <label class="newtic-cookie-setting"><span><strong>Zorunlu</strong><small>Güvenli oturum, sepet ve CSRF koruması</small></span><input type="checkbox" checked disabled aria-label="Zorunlu çerezler, daima etkin"></label>
    <label class="newtic-cookie-setting"><span><strong>Analitik</strong><small>İzin verirseniz anonim ziyaret, ürün ve arama istatistikleri ile son 5 dakikalık etkin oturumlar ölçülür. IP veya kesin konum kaydedilmez; olaylar en fazla 90 gün saklanır.</small></span><input type="checkbox" data-cookie-analytics></label>
    <label class="newtic-cookie-setting"><span><strong>Pazarlama</strong><small>Tercih verildiğinde ileride yapılandırılacak reklam teknolojileri</small></span><input type="checkbox" data-cookie-marketing></label>
    <p class="newtic-cookie-hint">Analitik tercihiniz varsayılan olarak kapalıdır. Kabul ederseniz NEWTIC'in birinci taraf ölçümü başlar. Geri çekerseniz bu tarayıcı oturumuna ait ölçümler silinmek üzere sunucuya istek gönderilir. Pazarlama araçları ayrıca izin gerektirir. Tercihlerinizi footer'dan değiştirebilirsiniz.</p>
    <div class="newtic-cookie-dialog-actions">
      <button type="button" data-cookie-close>Vazgeç</button>
      <button type="button" data-cookie-save>Tercihleri kaydet</button>
    </div>
  </div>
</dialog>
