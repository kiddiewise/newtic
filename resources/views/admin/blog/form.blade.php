@extends('admin.layout')
@section('title', ($post->exists ? 'Yazıyı Düzenle' : 'Yeni Blog Yazısı').' | NEWTIC')
@section('content')
<div class="admin-page-heading blog-admin-page-head">
  <div><a class="admin-back" href="{{ route('admin.blog.posts.index') }}">← Blog yazıları</a><p class="admin-eyebrow">İÇERİK STÜDYOSU</p><h1>{{ $post->exists ? 'Yazıyı düzenle' : 'Yeni blog yazısı' }}</h1><p>Öne çıkan görsel, içerik blokları ve arama motoru görünümünü yönetin.</p></div>
  @if($post->exists && $post->is_published && $post->published_at?->isPast())
    <a class="admin-secondary" href="{{ route('blog.show', $post->slug) }}" target="_blank" rel="noopener noreferrer">Yazıyı görüntüle ↗</a>
  @endif
</div>
<form method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route('admin.blog.posts.update', $post) : route('admin.blog.posts.store') }}" class="blog-admin-editor-form">
  @csrf
  @if($post->exists) @method('PATCH') @endif
  <div class="blog-admin-editor-grid">
    <div>
      <div class="admin-panel">
        <div class="admin-panel-title"><h2>Yazı bilgileri</h2></div>
        <div class="admin-form">
          <label>Yazı başlığı *<input name="title" maxlength="190" required value="{{ old('title', $post->title) }}" placeholder="Okuyucunun merakını uyandıran bir başlık"></label>
          <label>URL kısa adı<input name="slug" maxlength="190" value="{{ old('slug', $post->slug) }}" placeholder="otomatik-olusturulur"><small>Örn. /blog/kiyidan-balikcilik-rehberi</small></label>
          <label>Kısa özet<textarea name="excerpt" maxlength="500" rows="4" placeholder="Blog kartlarında ve meta açıklamasında kullanılır.">{{ old('excerpt', $post->excerpt) }}</textarea></label>
          <label>Yazar / Editör<input name="author_name" maxlength="100" placeholder="{{ \App\Support\StoreIdentity::name() }}" value="{{ old('author_name', $post->author_name) }}"></label>
        </div>
      </div>
      <div class="admin-panel">
        <div class="admin-panel-title"><h2>Öne çıkan görsel</h2></div>
        <p class="admin-description">Blog liste kartlarında ve yazının girişinde kullanılır. <strong>Önerilen: 1600 × 900 px (16:9)</strong>. WebP, AVIF, JPG veya PNG · en fazla 5 MB.</p>
        @if($post->featured_image_path)<img class="blog-admin-featured-preview" src="{{ asset($post->featured_image_path) }}" alt="Mevcut kapak görseli">@endif
        <div class="admin-form">
          <label>{{ $post->featured_image_path ? 'Kapak görselini değiştir' : 'Kapak görseli yükle' }}<input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp,image/avif"></label>
          <label>Görsel alternatif metni<input name="featured_image_alt" maxlength="190" value="{{ old('featured_image_alt', $post->featured_image_alt) }}" placeholder="Görselin içeriğini açıklayın"></label>
          @if($post->featured_image_path)
          <label class="admin-check"><input type="checkbox" name="remove_featured_image" value="1">Kapak görselini kaldır</label>
          @endif
        </div>
      </div>
      <div class="admin-panel">
        <div class="admin-panel-title"><h2>SEO ayarları</h2></div>
        <p class="admin-description">Boş bırakırsanız başlık ve özet kullanılır. Canlı domainde canonical adres otomatik oluşturulur.</p>
        <div class="admin-form">
          <label>SEO başlığı<input name="seo_title" maxlength="190" value="{{ old('seo_title', $post->seo_title) }}" placeholder="Arama sonucu başlığı"></label>
          <label>Meta açıklaması<textarea name="seo_description" maxlength="500" rows="3" placeholder="Arama sonuçlarında görünen kısa açıklama">{{ old('seo_description', $post->seo_description) }}</textarea></label>
          <label class="admin-check"><input type="checkbox" name="seo_noindex" value="1" @checked(old('seo_noindex', $post->seo_noindex))>Arama motorlarının bu yazıyı indekslemesini engelle</label>
        </div>
      </div>
      <div class="admin-panel">
        <div class="admin-panel-title"><h2>Kaynaklar</h2><span>İsteğe bağlı</span></div>
        <p class="admin-description">Bilginin kaynağını, başlığını ve bağlantısını ekleyin. Girilmeyen kaynak alanı sitede görünmez.</p>
        <div data-blog-sources>
          @foreach(old('sources', ($post->sources ?: [['title'=>'', 'url'=>'']])) as $i => $source)
            <div class="blog-admin-source-row" data-blog-source-row>
              <label>Kaynak adı<input name="sources[{{ $i }}][title]" maxlength="160" value="{{ $source['title'] ?? '' }}" placeholder="Örn. Resmî rehber"></label>
              <label>Bağlantı<input name="sources[{{ $i }}][url]" type="url" maxlength="1500" value="{{ $source['url'] ?? '' }}" placeholder="https://..."></label>
              <button class="blog-admin-remove-source" type="button" data-blog-source-remove aria-label="Kaynak satırını kaldır">✕</button>
            </div>
          @endforeach
        </div>
        <template id="blog-source-template">
          <div class="blog-admin-source-row" data-blog-source-row>
            <label>Kaynak adı<input name="sources[__INDEX__][title]" maxlength="160" placeholder="Kaynak başlığı"></label>
            <label>Bağlantı<input name="sources[__INDEX__][url]" type="url" maxlength="1500" placeholder="https://..."></label>
            <button class="blog-admin-remove-source" type="button" data-blog-source-remove aria-label="Kaynak satırını kaldır">✕</button>
          </div>
        </template>
        <button class="admin-secondary" type="button" data-blog-source-add>+ Kaynak ekle</button>
      </div>
    </div>
    <aside class="blog-admin-sidebar">
      <div class="admin-panel">
        <div class="admin-panel-title"><h2>Yayın ayarları</h2></div>
        <div class="admin-form">
          <label>Kategori
            <select name="blog_category_id">
              <option value="">— Kategorisiz —</option>
              @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(old('blog_category_id', $post->blog_category_id) == $category->id)>{{ $category->name }}</option>
              @endforeach
            </select>
          </label>
          <a class="blog-admin-tiny-link" href="{{ route('admin.blog.categories.index') }}">+ Kategori yönetimi</a>
          <label class="blog-admin-publish-toggle"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $post->is_published))><span><strong>Yayına al</strong><small>Kapalıyken taslak olarak kalır ve ziyaretçilere görünmez.</small></span></label>
          @if($post->exists)<small class="blog-admin-muted">Son düzenleme: {{ $post->updated_at?->format('d.m.Y H:i') }}</small>@endif
          <button class="admin-primary blog-admin-save-btn" type="submit">{{ $post->exists ? 'Yazı ayarlarını kaydet' : 'Yazıyı oluştur' }}</button>
        </div>
      </div>
      <div class="blog-admin-tip-card"><span aria-hidden="true">✦</span><strong>Editör ipucu</strong><p>Önce yazıyı oluşturun. Ardından aşağıdan başlık, paragraf, tek görsel ve slider bloklarını dilediğiniz sırayla ekleyin.</p></div>
    </aside>
  </div>
</form>
@if($post->exists)
<section class="admin-panel blog-admin-blocks" id="blog-blocks" aria-labelledby="blog-block-title">
  <div class="admin-panel-title"><h2 id="blog-block-title">İçerik blokları</h2><span>{{ $post->blocks->count() }} blok</span></div>
  <p class="admin-description">Bloklar yukarıdan aşağıya okunur. Oklarla sırayı değiştirebilirsiniz; her hareket otomatik kaydedilir. Metinler güvenli düz yazı olarak gösterilir.</p>
  @foreach($post->blocks as $block)
    <article class="blog-admin-block" id="blog-block-{{ $block->id }}">
      <div class="blog-admin-block-head">
        <div><span class="blog-admin-block-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><strong>{{ ['heading'=>'Başlık','text'=>'Metin','image'=>'Tekli görsel','slider'=>'Görsel slider'][$block->type] ?? 'Blok' }}</strong></div>
        <div class="blog-admin-block-actions">
          <form action="{{ route('admin.blog.blocks.move', [$post, $block]) }}" method="POST">@csrf<input type="hidden" name="direction" value="up"><button class="blog-admin-icon-btn" title="Yukarı taşı" aria-label="Bloğu yukarı taşı" @disabled($loop->first)><x-admin-icon name="arrow-up"/></button></form>
          <form action="{{ route('admin.blog.blocks.move', [$post, $block]) }}" method="POST">@csrf<input type="hidden" name="direction" value="down"><button class="blog-admin-icon-btn" title="Aşağı taşı" aria-label="Bloğu aşağı taşı" @disabled($loop->last)><x-admin-icon name="arrow-down"/></button></form>
          <form method="POST" action="{{ route('admin.blog.blocks.destroy', [$post, $block]) }}" onsubmit="return confirm('İçerik bloğu ve bu bloğun görselleri silinsin mi?')">@csrf @method('DELETE')<button class="blog-admin-icon-btn blog-admin-icon-danger" title="Bloğu sil" aria-label="Bloğu sil"><x-admin-icon name="trash-2"/></button></form>
        </div>
      </div>
      <form action="{{ route('admin.blog.blocks.update', [$post, $block]) }}" method="POST" enctype="multipart/form-data" class="admin-form">
        @csrf @method('PATCH')
        @if(in_array($block->type, ['heading','text']))
          <label>{{ $block->type === 'heading' ? 'Ara başlık (H2)' : 'Paragraf metni' }}
            <textarea name="content" rows="{{ $block->type === 'heading' ? 2 : 8 }}" maxlength="{{ $block->type === 'heading' ? 190 : 30000 }}" required>{{ $block->content }}</textarea>
          </label>
        @else
          <div class="blog-admin-media-spec">{{ $block->type === 'slider' ? 'Slider: 1600 × 900 px · en çok 12 görsel' : 'Tekli görsel: 1600 × 1000 px' }} · JPG, PNG, WebP, AVIF</div>
          <label>{{ $block->type === 'slider' ? 'Slider’a yeni görseller ekle' : 'Görseli değiştir' }}
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/avif" {{ $block->type === 'slider' ? 'multiple' : '' }}>
          </label>
          <label>Yeni görsellerin alternatif metni<input name="alt" maxlength="190" placeholder="Görsel açıklaması"></label>
          <label>Yeni görsellerin alt yazısı<input name="caption" maxlength="300" placeholder="İsteğe bağlı"></label>
        @endif
        <div class="admin-form-actions"><button class="admin-secondary" type="submit">Bloğu kaydet</button></div>
      </form>
      @if(in_array($block->type, ['image','slider']))
      <div class="blog-admin-image-grid">
        @foreach($block->images as $image)
        <div class="blog-admin-image-item">
          <img src="{{ asset($image->path) }}" alt="" loading="lazy">
          <form class="admin-form" method="POST" action="{{ route('admin.blog.images.update', [$post, $block, $image]) }}">
            @csrf @method('PATCH')
            <label>Alternatif metin<input name="alt" maxlength="190" value="{{ $image->alt }}"></label>
            <label>Alt yazı<input name="caption" maxlength="300" value="{{ $image->caption }}"></label>
            <label>Sıra<input name="sort_order" type="number" min="0" max="10000" value="{{ $image->sort_order }}" required></label>
            <button class="admin-secondary" type="submit">Görseli kaydet</button>
          </form>
          <form method="POST" action="{{ route('admin.blog.images.destroy', [$post, $block, $image]) }}" onsubmit="return confirm('Bu görseli kaldırmak istiyor musunuz?')">
            @csrf @method('DELETE') <button type="submit" class="blog-admin-delete-image">Görseli kaldır</button>
          </form>
        </div>
        @endforeach
      </div>
      @endif
    </article>
  @endforeach
  <div class="blog-admin-add-block">
    <h3>+ Yeni içerik bloğu</h3>
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.blog.blocks.store', $post) }}" class="admin-form blog-admin-new-block-form" data-blog-new-block>
      @csrf
      <label>Blok türü
        <select name="type" data-blog-block-type>
          <option value="text">Paragraf / metin</option>
          <option value="heading">Ara başlık</option>
          <option value="image">Tekli görsel</option>
          <option value="slider">Görsel slider</option>
        </select>
      </label>
      <label data-blog-block-text>Metin veya başlık
        <textarea name="content" rows="5" maxlength="30000" placeholder="İçerik metnini buraya yazın..."></textarea>
      </label>
      <div data-blog-block-images hidden>
        <label>Görsel dosyaları
          <input type="file" name="images[]" data-blog-block-file accept="image/jpeg,image/png,image/webp,image/avif">
        </label>
        <p class="blog-admin-media-spec">Tekli görsel 1600 × 1000 px, slider görselleri 1600 × 900 px · en fazla 5 MB / görsel.</p>
        <label>Alternatif metin<input name="alt" maxlength="190" placeholder="Görseli açıklayın"></label>
        <label>Alt yazı<input name="caption" maxlength="300" placeholder="İsteğe bağlı"></label>
      </div>
      <button class="admin-primary" type="submit">Blok ekle</button>
    </form>
  </div>
</section>
@endif
@endsection
