<p>{{ $event->title }} への参加が確定しました。</p>
<p>キャンセル待ちから繰り上がり、申し込みが完了しています。</p>
<p>開催日時: {{ $event->event_date->format('Y年m月d日 H:i') }} 〜 {{ $event->end_date->format('H:i') }}</p>
<p>開催場所: {{ $event->prefecture }} {{ $event->location }}</p>
<p><a href="{{ route('events.show', $event) }}">イベントページを見る</a></p>
