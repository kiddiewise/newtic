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
    <label class="newtic-cookie-setting"><span><strong>Analitik</strong><small>Tercih verildiğinde ileride yapılandırılacak ölçüm araçları</small></span><input type="checkbox" data-cookie-analytics></label>
    <label class="newtic-cookie-setting"><span><strong>Pazarlama</strong><small>Tercih verildiğinde ileride yapılandırılacak reklam teknolojileri</small></span><input type="checkbox" data-cookie-marketing></label>
    <p class="newtic-cookie-hint">Bu mağazada ilgili araç henüz tanımlanmadıysa tercihiniz herhangi bir takip sistemi başlatmaz. Tercihinizi footer'dan değiştirebilirsiniz.</p>
    <div class="newtic-cookie-dialog-actions">
      <button type="button" data-cookie-close>Vazgeç</button>
      <button type="button" data-cookie-save>Tercihleri kaydet</button>
    </div>
  </div>
</dialog>
