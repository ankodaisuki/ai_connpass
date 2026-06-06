<p>{{ $event->title }} は中止になりました。</p>
<p>開催予定日時: {{ $event->event_date->format('Y年m月d日 H:i') }} 〜 {{ $event->end_date->format('H:i') }}</p>
<p>開催予定場所: {{ $event->prefecture }} {{ $event->location }}</p>
<p>ご不便をおかけして申し訳ありません。</p>
<p><a href="{{ route('events.show', $event) }}">イベントページを見る</a></p>
