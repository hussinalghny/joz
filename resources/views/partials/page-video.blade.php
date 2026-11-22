<figure class="page-video">
  <video controls playsinline preload="metadata" @if ($poster = $page->videoPosterUrl()) poster="{{ $poster }}" @endif>
    <source src="{{ $video }}" type="{{ $page->videoMimeType() }}">
  </video>
</figure>
