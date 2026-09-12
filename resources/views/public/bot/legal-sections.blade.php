{{--
    Shared renderer for the structured legal payload produced by
    LegalDocumentService. Included by the terms and privacy bot views so the
    indexed text can never drift from what the Inertia page shows.
--}}
@php
    $contact = $document['contact'] ?? [];
@endphp

<article itemscope itemtype="https://schema.org/WebPage">
    <h1 itemprop="name">{{ $document['title'] }}</h1>
    <p>{{ $document['lead'] }}</p>
    <p>
        <span>{{ $document['document_kind'] }}</span> —
        <time datetime="{{ $document['last_updated'] }}">terakhir diperbarui {{ $document['last_updated_label'] }}</time>.
        <meta itemprop="dateModified" content="{{ $document['last_updated'] }}">
    </p>
    <p>Diterbitkan oleh {{ $document['publisher'] }}.</p>

    <nav aria-label="Dokumen hukum">
        <ul>
            @foreach ($legalDocuments as $item)
                <li>
                    <a href="{{ url($item['path']) }}"@if ($item['type'] === $document['type']) aria-current="page"@endif>{{ $item['title'] }}</a>
                </li>
            @endforeach
        </ul>
    </nav>

    <h2>Daftar Isi</h2>
    <ol>
        @foreach ($document['sections'] as $section)
            <li><a href="#{{ $section['id'] }}">{{ $document['article_label'] }} {{ $loop->iteration }} — {{ $section['title'] }}</a></li>
        @endforeach
    </ol>

    @foreach ($document['sections'] as $section)
        <section id="{{ $section['id'] }}">
            <h2>{{ $document['article_label'] }} {{ $loop->iteration }} — {{ $section['title'] }}</h2>
            @foreach ($section['blocks'] as $block)
                @if ($block['type'] === 'paragraph')
                    <p>{!! $block['text'] !!}</p>
                @elseif ($block['type'] === 'subheading')
                    <h3>{!! $block['text'] !!}</h3>
                @elseif ($block['type'] === 'list')
                    <ul>
                        @foreach ($block['items'] as $item)
                            <li>{!! $item !!}</li>
                        @endforeach
                    </ul>
                @elseif ($block['type'] === 'contact')
                    <address>
                        <p>{{ $block['contact']['name'] }}</p>
                        @if (! empty($block['contact']['email']))
                            <p>Email: <a href="mailto:{{ $block['contact']['email'] }}">{{ $block['contact']['email'] }}</a></p>
                        @endif
                        @if (! empty($block['contact']['phone']))
                            <p>Telepon: <a href="tel:{{ $block['contact']['phone'] }}">{{ $block['contact']['phone'] }}</a></p>
                        @endif
                        @if (! empty($block['contact']['address']))
                            <p>Alamat: {{ $block['contact']['address'] }}</p>
                        @endif
                    </address>
                @endif
            @endforeach
        </section>
    @endforeach

    <p>
        Tautan terkait:
        <a href="{{ url('/') }}">Beranda</a> —
        <a href="{{ url($document['type'] === 'terms' ? 'privacy' : 'terms') }}">
            {{ $document['type'] === 'terms' ? 'Kebijakan Privasi' : 'Syarat Layanan' }}
        </a>
    </p>
</article>
