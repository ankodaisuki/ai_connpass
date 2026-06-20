<p>{{ $event->title }} は運営により削除されました。</p>
<p>開催予定日時: {{ $event->event_date->format('Y年m月d日 H:i') }}</p>
<p>削除理由: {{ $reason }}</p>
<p>ご不便をおかけして申し訳ありません。</p>
