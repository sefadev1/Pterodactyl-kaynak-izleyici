# Ptero Resource Watch
![PHP](https://img.shields.io/badge/PHP-8+-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

Pterodactyl paneli icin hazirlanmis, web tabanli ve acik kaynak bir izleme arayuzudur.

Bu proje iki ana ekran sunar:

- `Sunucular`: Sunucularin durumunu, anlik kaynak kullanimini, trendlerini ve detayli gecmisini gosterir.
- `Node'lar`: Node bazinda toplam RAM, depolama, cekirdek ve sunucu dagilimini ozetler.

## Ne Ise Yarar?

- Paneldeki tum sunuculari tek yerden takip etmeyi kolaylastirir.
- En cok kaynak tuketen sunuculari hizlica bulmana yardimci olur.
- Node kapasitesini ve node tarafindaki yogunlugu daha net gosterir.
- Hafif PHP yapisi sayesinde kolay kurulur, kolay duzenlenir ve kolay paylasilir.

## Guncel Ozellikler

- Ayrik sayfa yapisi: `servers.php` ve `nodes.php`
- Canli API modu ve demo modu
- CPU, RAM, disk, ag ve uptime takibi
- Gelismis sunucu filtreleme:
  - durum
  - tur
  - node
  - arama
  - siralama
- Durum panosu:
  - toplam
  - acik
  - kapali
  - kuruluyor
  - uyari veren
- Ic goru kartlari:
  - en yuksek CPU
  - en yuksek RAM
  - ag lideri
  - en uzun uptime
- Sekmeli trend alani:
  - CPU
  - RAM
  - Disk
- Farkli zaman pencereleri:
  - son 15 kayit
  - son 60 kayit
  - tum kayitlar
- Acilir detay paneli:
  - sunucu kimligi
  - owner
  - node
  - limitler
  - ham durum
  - trafik ozeti
  - uptime
  - trend ozeti
- Detay icinde sunucu bazli mini gecmis grafikleri:
  - CPU gecmisi
  - RAM gecmisi
  - Disk gecmisi
- Cok sayida sunucu icin sayfalama
- Dosya tabanli gecmis kaydi

## Ekranlar

### 1. Sunucular

`/servers.php` sayfasi profesyonel bir izleme ekranidir.

Icerir:

- genel ozet kartlari
- durum sayac kartlari
- hizli ic goru kartlari
- gelismis filtre alani
- sekmeli trend grafigi
- acilir detay satirli sunucu tablosu

### 2. Node'lar

`/nodes.php` sayfasi node tarafini ayri takip etmek icin hazirlanmistir.

Icerir:

- toplam node kapasitesi
- node bazli kullanim yuzdeleri
- toplam sunucu sayisi
- node altindaki sunucu gruplari

## Proje Yapisi

```text
public/
  index.php           -> varsayilan yonlendirme
  servers.php         -> sunucu izleyicisi
  nodes.php           -> node izleyicisi
  api/overview.php    -> frontend icin JSON veri cikisi
  assets/
    app.js            -> arayuz mantigi
    styles.css        -> tema ve yerlesim

src/
  bootstrap.php       -> yukleme ve config yardimcilari
  HttpClient.php      -> HTTP istek katmani
  PterodactylService.php
  MetricsAggregator.php
  HistoryStore.php

storage/
  history.json        -> trend gecmisi
```

## Kurulum

1. `config.example.php` dosyasini `config.php` olarak kopyala.
2. Pterodactyl bilgilerini doldur:
   - `panel_url`
   - `client_api_key`
   - gerekiyorsa `application_api_key`
3. Gercek verilerle calismak icin `demo_mode` degerini `false` yap.
4. Uygulamayi baslat:

```powershell
php -S 127.0.0.1:8080 -t public
```

Alternatif olarak:

```powershell
php -S 127.0.0.1:8080 router.php
```

5. Tarayicidan `http://127.0.0.1:8080` adresini ac.

## Ornek Yapilandirma

Repoda `config.example.php` bulunur. Kendi anahtarlarini `config.php` icine yazman yeterlidir.

## API Anahtarlari

- `client_api_key`: Sunucularin canli `resources` verisini almak icin kullanilir.
- `application_api_key`: Tum sunucu envanterini ve node bilgisini cekmek icin kullanilir.

En iyi sonuc icin iki anahtarin da tanimli olmasi onerilir.

## Durum Mantigi

Arayuzde durumlar uc baslikta toplanir:

- `Acik`
- `Kapali`
- `Kuruluyor`

Gorunen durum, Pterodactyl cevabi ile canli metriklerin birlikte degerlendirilmesiyle hesaplanir.

## Gelecek Fikirleri

- Discord bot entegrasyonu
- Uyari ve alarm sistemi
- Daha gelismis node grafikleri
- CSV veya JSON disa aktarma
- Rol bazli giris sistemi

## Lisans

Bu proje`MIT` lisansi ile lisanslanmıştır.
