=== CookieKit — Çerez ve Onay Yöneticisi ===
Contributors: rbnbilisim
Tags: cookie consent, kvkk, gdpr, consent mode, cerez
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bağlantı: https://github.com/RbnCore

KVKK uyumlu çerez bandı ve tercih paneli, Google Consent Mode v2, onay kaydı ve betik engelleme. Yalnız güncelleme kontrolü için GitHub'a bağlanır (ayarlardan kapatılabilir).

== Description ==

* Türkçe banner ve tercih paneli; Kabul et / Reddet / Tercihler düğmeleri eşit görünürlükte (karanlık desen yok).
* Kategoriler: Zorunlu (kilitli), Analiz, Reklam. Her kategori için belgelenmiş çerez listesi.
* Google Consent Mode v2: sayfa başında varsayılan "denied"; seçimde `consent update`; geri gelen ziyaretçide anında uygulanır.
* Onay çerezi `cookiekit_consent` (sürüm.bitmask), 180 gün; "revizyon" artırılınca herkesten yeniden onay istenir.
* Onay kaydı (KVKK ispat): ham IP saklanmaz (günlük dönen tuzla HMAC), 12 ay sonra otomatik silinir, CSV dışa aktarım.
* Kısa kodlar: `[cookiekit_tercihler]`, `[cookiekit_cerez_listesi]`; herhangi bir öğeye `data-cookiekit-open` verilirse paneli açar.
* Betik engelleme API'si: `cookiekit_script( 'analytics', $src_veya_kod )`.
* Hafif: JS ve CSS toplamı birkaç KB, jQuery yok, CLS 0; tek dış istek güncelleme kontrolüdür (ayarlardan kapatılabilir).

== Installation ==

1. `cookiekit.zip` dosyasını Eklentiler → Yeni ekle → Eklenti yükle ile yükleyin ve etkinleştirin.
2. Ayarlar → CookieKit ekranından (Genel, Görünüm, Metinler, Çerezler, Onay kayıtları, Entegrasyon, Yardım sekmeleri) kategorileri, bağlantıları, renkleri ve metinleri düzenleyin; ilk kurulumda 3 adımlı sihirbaz açılır.

== Changelog ==

= 0.1.2 =
* GitHub’dan güncelleme: Eklentiler sayfasında Güncelle / güncel, WordPress otomatik güncelleme desteği.
* Paket SHA-256 ve Ed25519 imzasıyla doğrulanır; Yardım → Güncelleme kartından kontrol kapatılabilir.

= 0.1.1 =
* İlk sürüm.
