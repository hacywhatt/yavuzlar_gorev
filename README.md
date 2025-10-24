# Bilet Satın Alma Platformu

Bu proje, modern web teknolojileri kullanılarak geliştirilmiş, dinamik ve çok kullanıcılı bir otobüs bileti satış ve yönetim platformudur. Projenin kurulumu ve teslimi, zorunlu olarak **Docker** kullanılarak gerçekleştirilmiştir.



## 💻 Proje Detayları

* **Amaç:** Modern web teknolojilerini kullanarak dinamik, veritabanı destekli ve çok kullanıcılı bir otobüs bileti satış platformu geliştirmektir
* **Teknolojiler:**
  **Programlama Dili:** PHP
  **Veritabanı:** SQLite
  **Arayüz:** HTML & CSS (İsteğe bağlı olarak Bootstrap gibi bir CSS framework'ü kullanılabilir)
  

## 🔑 Kullanıcı Rolleri ve Yetkilendirme

Platform, üç farklı kullanıcı rolünü destekleyecektir: Admin, Firma Admin ve User (Yolcu).

| Rol | Ana Görevler | Kritik Kısıtlamalar |
| :--- | :--- | :--- |
| **Admin** | Yeni firmalar ve Firma Adminleri oluşturabilir, indirim kuponlarını yönetebilir (CRUD). |Sistemdeki en yetkili roldür. |
| **Firma Admin** | Sadece kendi firmasına ait seferleri (oluşturma, düzenleme, silme) yönetebilir. | Kendi firması dışındaki seferlere ve genel sistem yönetimine erişemez. |
| **User (Yolcu)** |Sisteme kayıt olabilir ve giriş yapabilir. Seferleri listeleyebilir, bilet satın alabilir/iptal edebilir. | Bilet alımı sanal kredi üzerinden yapılır. Kalkış saatine son 1 saatten az kalmışsa bilet iptaline izin verilmez. |
| **Ziyaretçi** | Ana sayfada seferleri listeleyebilir ve sefer detaylarını görebilir. | Bilet satın alma butonuna tıkladığında "Lütfen Giriş Yapın" uyarısı alır. |

## 🚀 Kurulum ve Çalıştırma (Docker)

Bu projeyi yerel makinenizde çalıştırmak için sisteminizde **Docker Desktop** (ve Windows için WSL 2) kurulu olmalıdır.

### 

Projeyi klonlayın ve dizine girin:

```bash
git clone https://github.com/hacywhatt/yavuzlar_gorev
cd yavuzlar_gorev

Servisi oluşturur ve başlatır. Port 8080 kullanılacaktır.
docker compose up -d

Container başarıyla çalıştıktan sonra, tarayıcınızı açın ve projeye erişin:

http://localhost:8080
Projeyi durdurmak ve containerları kaldırmak için:
docker compose down



## 🔑 Test Için Kullanıcı Bilgileri

| Rol | Kullanıcı Adı | Şifre | Açıklama |
|-----|----------|--------|-----------|
|  Admin | admin| 123456 | Firma ve kupon yönetimi |
|  Firma Admini | sibervatan_admin | 123456 | Kendi firmasına ait sefer CRUD + satış/iptal |
|  Kullanıcı | test | test123 | Sefer arama, bilet satın alma, iptal, PDF indir |

## 💼 Rollere Göre Yetkiler

| İşlem | Admin | Firma Admin | Kullanıcı |
|-------|:------:|:------------:|:----------:|
| Sefer Ekle/Sil | ✅ | ✅ (kendi firması) | ❌ |
| Firma Ekle/Sil | ✅ | ❌ | ❌ |
| Firma Admin Atama | ✅ | ❌ | ❌ |
| Kupon Yönetimi | ✅ | ❌ | ❌ |
| Bilet Satın Alma | ❌ | ❌ | ✅ |
| Bilet İptal Etme | ❌ | ✅ | ✅ |
| Bilet PDF Görüntüleme | ❌ | ❌ | ✅ |

> ⏰ Bilet iptali yalnızca kalkıştan **en az 1 saat önce** yapılabilir.
