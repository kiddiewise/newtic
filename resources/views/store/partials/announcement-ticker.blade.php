@if($shopAnnouncements->isNotEmpty())
  <div class="announcement-ticker" aria-label="Mağaza duyuruları">
    <span class="announcement-ticker-icon" aria-hidden="true"><x-store-icon name="sparkles" /></span>
    <div class="announcement-ticker-window">
      <div class="announcement-ticker-track" data-announcement-ticker>
        @foreach($shopAnnouncements as $notice)
          <span class="announcement-ticker-message">{{ $notice->message }}</span>
          <span class="announcement-ticker-separator" aria-hidden="true">✦</span>
        @endforeach
        @foreach($shopAnnouncements as $notice)
          <span class="announcement-ticker-message" aria-hidden="true">{{ $notice->message }}</span>
          <span class="announcement-ticker-separator" aria-hidden="true">✦</span>
        @endforeach
      </div>
    </div>
  </div>
@endif
