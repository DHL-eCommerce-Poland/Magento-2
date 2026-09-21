# Oficjalna wtyczka DHL eCommerce (Poland) dla Magento2 obsługująca wysyłkę kurierem i do punktów, w kraju oraz zagranicą.

Oficjalny moduł integracyjny DHL eCommerce (Poland) dla platformy Magento 2. Umożliwia kompleksową obsługę przesyłek kurierskich oraz dostaw do sieci punktów odbioru i automatów paczkowych (DHL POP / Parcel Locker), zarówno na terenie Polski, jak i w przesyłkach międzynarodowych.

---

## Wymagania systemowe

* **Magento Open Source / Adobe Commerce**: `>= 2.4.4`
* **PHP**: `>= 8.1`
* **Wymagane rozszerzenia PHP**:
  * `ext-soap`
  * `ext-json`
  * `ext-mbstring`
  * `ext-zip`

---

## Metody instalacji

Wtyczkę można zainstalować na dwa sposoby: za pomocą menedżera pakietów **Composer** (zalecane) lub ręcznie poprzez **plik ZIP**.

---

### Sposób 1: Instalacja przez Composer (zalecane)

1. **Pobierz moduł za pomocą Composera**:
   ```bash
   composer require ideo/module-dhl
   ```

2. **Włącz moduł i zaktualizuj Magento**:
   ```bash
   bin/magento module:enable Ideo_Dhl
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```
   *(Uwaga: w trybie produkcyjnym należy dodatkowo wdrożyć pliki statyczne poleceniem `bin/magento setup:static-content:deploy`).*

---

### Sposób 2: Instalacja ręczna z pliku ZIP

1. **Pobierz archiwum ZIP**:
   * Repozytorium wtyczki: [https://github.com/DHL-eCommerce-Poland/Magento-2](https://github.com/DHL-eCommerce-Poland/Magento-2)
   * Pobierz najnowszą wersję z sekcji [Releases](https://github.com/DHL-eCommerce-Poland/Magento-2/releases) lub bezpośrednio jako [ZIP z gałęzi głównej](https://github.com/DHL-eCommerce-Poland/Magento-2/archive/refs/heads/master.zip).

2. **Przygotuj strukturę katalogów w Magento 2**:
   W głównym katalogu swojej instalacji Magento 2 utwórz ścieżkę katalogów (jeśli jeszcze nie istnieją):
   ```bash
   mkdir -p app/code/Ideo/Dhl
   ```

3. **Rozpakuj zawartość archiwum**:
   Wypakuj zawartość archiwum ZIP do utworzonego folderu `app/code/Ideo/Dhl/`.
   
   > **Ważne:** Upewnij się, że pliki modułu (takie jak `registration.php`, `composer.json` oraz katalog `etc/`) znajdują się bezpośrednio w katalogu:
   > `app/code/Ideo/Dhl/` (a nie w dodatkowym podfolderze, np. `app/code/Ideo/Dhl/Magento-2-main/`).

4. **Włącz moduł i zaktualizuj Magento**:
   W głównym katalogu Magento 2 wykonaj następujące polecenia:
   ```bash
   bin/magento module:enable Ideo_Dhl
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```
   *(Uwaga: w trybie produkcyjnym należy dodatkowo wdrożyć pliki statyczne poleceniem `bin/magento setup:static-content:deploy`).*

---

## Konfiguracja w panelu administracyjnym

Po pomyślnej instalacji przejdź do panelu administracyjnego Magento 2:

1. Przejdź do: **Sklepy (Stores)** &rarr; **Konfiguracja (Configuration)** &rarr; **Sprzedaż (Sales)** &rarr; **Metody dostawy (Delivery Methods / Shipping Methods)** &rarr; sekcja **DHL Parcel** (lub bezpośrednio przez menu: **Sprzedaż (Sales)** &rarr; **DHL Parcel** &rarr; **Configuration**).
2. Wprowadź dane uwierzytelniające API DHL oraz dostosuj parametry usług (dostawy kurierskie, punkty odbioru POP/automaty paczkowe, ubezpieczenia, gabaryty przesyłek).
3. Zapisz konfigurację i odśwież pamięć podręczną (Cache).

---

## Aktualizacja modułu

### Przez Composer:
```bash
composer update ideo/module-dhl
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```
*(W trybie produkcyjnym: `bin/magento setup:static-content:deploy`).*

### Przez plik ZIP:
1. Pobierz nową wersję archiwum.
2. Zastąp istniejące pliki w katalogu `app/code/Ideo/Dhl/`.
3. Wykonaj polecenia aktualizacyjne Magento (`setup:upgrade`, `setup:di:compile`, `cache:flush` oraz `setup:static-content:deploy` w trybie produkcyjnym).

---

## Licencja

Moduł udostępniany jest na licencji [MIT](LICENSE).
