<?php echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
{{-- O `<?xml` sai por echo e na PRIMEIRA linha: escrito à mão o Blade lia o
     `<?` como PHP, e qualquer coisa antes dele (até uma linha em branco) torna
     o XML inválido. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach($urls as $u)
    <url>
        <loc>{{ $u['loc'] }}</loc>
@if(!empty($u['lastmod']))
        <lastmod>{{ $u['lastmod'] }}</lastmod>
@endif
        <changefreq>{{ $u['changefreq'] }}</changefreq>
        <priority>{{ $u['priority'] }}</priority>
@if(!empty($u['imagem']))
        <image:image>
            <image:loc>{{ $u['imagem'] }}</image:loc>
@if(!empty($u['titulo']))
            <image:title>{{ $u['titulo'] }}</image:title>
@endif
        </image:image>
@endif
    </url>
@endforeach
</urlset>
