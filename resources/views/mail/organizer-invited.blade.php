<p>{{ $inviter->name }} さんから、イベント「{{ $event->title }}」の合同主催に招待されました。</p>

<p>下記のページから、招待を承諾または辞退できます。</p>

<p><a href="{{ route('my.organizer-invitations') }}">{{ route('my.organizer-invitations') }}</a></p>

<p>承諾すると、あなたはこのイベントの合同主催者として、イベントの編集や出欠記録ができるようになります。承諾するまで、あなたの名前が公開ページに表示されることはありません。</p>
