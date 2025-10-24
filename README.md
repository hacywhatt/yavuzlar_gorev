Bilet Satın Alma Platformu
Bu proje, modern web teknolojileri kullanılarak geliştirilmiş, dinamik ve çok kullanıcılı bir otobüs bileti satış ve yönetim platformudur.
Proje Detayları
Amaç: Dinamik, veritabanı destekli bir otobüs bileti satış sistemi oluşturmak.
Teknolojiler:
Programlama Dili: PHP 
Veritabanı: SQLite 
Arayüz: HTML & CSS (Temel stil)
Paketleme/Dağıtım: Docker ve Docker Compose 
Kullanıcı Rolleri ve Yetkilendirme
Platform, dört ana kullanıcı rolünü ve yetki mimarisini desteklemektedir.
Kurulum ve Çalıştırma (Docker)
Bu projeyi yerel makinenizde çalıştırmak için sisteminizde Docker Desktop (ve Windows için WSL 2) kurulu olmalıdır.
Dosyaları İndirme
Projeyi klonlayın veya ZIP olarak indirin:Bashgit clone [DEPO ADRESİNİZ] bilet-satin-alma
cd bilet-satin-alma
Veritabanını Hazırlama
Projeye dahil edilen database.sqlite dosyasının ana dizinde bulunduğundan emin olun. 
Docker ile Ayağa Kaldırma
Proje dizini içerisindeyken, docker-compose.yml dosyasını kullanarak container'ı oluşturun ve arka planda başlatın:Bash# Servisi oluşturur ve başlatır
docker compose up -d
Projeye Erişim
Container'ın başlatılmasından kısa bir süre sonra (yaklaşık 10-20 saniye), tarayıcınızı açın ve projeye erişin:
http://localhost:8080
Durdurma
Projeyi durdurmak için:
Bashdocker compose down
Temel Geliştirme Adımları
Projede izlenen ana geliştirme akışı:
Veritabanı Kurulumu:
SQLite şeması ve test verileri oluşturuldu.
Kullanıcı Sistemi: 
Kayıt/Giriş/Çıkış işlemleri ve Session yönetimi entegre edildi.
Rol Yönetimi:
Admin, Firma Admin ve User rolleri tanımlandı, yetkisiz erişim engellendi.
Sefer Listeleme: 
Ana sayfada arama ve listeleme arayüzü hazırlandı.
CRUD Panelleri: 
Firma Admin (Sefer CRUD) ve Admin (Firma, Kupon CRUD) panelleri geliştirildi.
Satın Alma:
Koltuk seçimi, kupon kodu uygulama ve bakiye düşme işlemleri uygulandı.
Bilet İptali: 
1 saat kuralı ile iadeli bilet iptal mekanizması geliştirildi.
PDF Bilet Üretimi:
FPDF kütüphanesi kullanılarak bilet çıktısı oluşturuldu.
