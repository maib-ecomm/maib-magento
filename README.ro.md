[![N|Solid](https://www.maib.md/images/logo.svg)](https://www.maib.md)

# Maib Payment Gateway pentru platforma Magento 2
Acest modul vă permite să integrați magazinul dvs. online cu noul **API e-commerce** de la **Modulul Maib Payment Gateway** pentru a accepta plăți online (Visa / Mastercard / Google Pay / Apple Pay).

## Descriere
Cu etapele de integrare și cerințele către website puteți face cunoștință [aici](https://docs.maibmerchants.md/ro/etape-si-cerinte-pentru-integrare).

Pentru a testa integrarea veți avea nevoie de datele de acces a unui Proiect de Test (Project ID / Proejct Secret / Signature Key). Pentru aceasta vă rugăm să veniți cu o solicitare la adresa de email: ecom@maib.md.

Pentru a efectua plăți reale, trebuie să aveți contractul e-commerce semnat și să efectuați cel puțin o tranzacție reușită folosind datele Proiectului de Test și datele cardului pentru teste. 

După semnarea contractului veți primi acces la platforma maibmerchants și veți avea posibilitatea să activați Proiectul de Producție.

## Funcțional
**Plăți online**: Visa / Mastercard / Apple Pay / Google Pay.

**Trei valute**: MDL / USD / EUR (în dependență de setările Proiectului dvs).

**Returnare plată**: Pentru a returna plata, este necesar să actualizați starea comenzii (vedeți _refund.png_) la starea selectată pentru _Plată returnată_ în setările extensiei **Modulul Maib Payment Gateway** (vedeți _settings-general.png_ , _settings-maibmerchants.png_ and _settings-order-status.png_). Suma plății va fi returnată pe cardul clientului.

## Cerințe 
- Înregistrare pe platforma maibmerchants.md
- Magento 2 platforma
- extensiile _curl_ and _json_ activate

## Installation
1. Descărcați fișierul de extensie din Github sau Magento.
2. În panoul de administrare Magento 2, accesați _Magazine_ -> _Configurare_ -> _Vânzări_ -> _Metode de plată_.
3. Găsiți modulul **Maib Payment Gateway Module** în listă (_Alte metode de plată_).
4. Alegeți _Da_ din butonul câmpului _Activ_ și Magento 2 va începe procesul de instalare, astfel încât modulul să fie activat.

## Setări
1. Project ID - Project ID din maibmerchants.md
2. Project Secret - Project Secret din maibmerchants.md. Este disponibil după activarea proiectului.
3. Signature Key - Signature Key pentru validarea notificărilor pe Callback URL. Este disponibil după activarea proiectului.
4. Ok URL / Fail URL / Callback URL - adăugați aceste link-uri în câmpurile respective ale setărilor Proiectului în maibmerchants.
5. Plată în așteptare - Starea comenzii când plata este în așteptare.
6. Plată cu succes - Starea comenzii când plata este finalizată cu succes.
7. Plată eșuată - Starea comenzii când plata a eșuat.
8. Platã returnatã - Starea comenzii când plata este returnată. Pentru returnarea plății, actualizați starea comenzii la starea selectată aici (vedeți _refund.png_).

## Depanare
Dacă aveți nevoie de asistență suplimentară, vă rugăm să nu ezitați să contactați echipa de asistență ecommerce **Modulul Maib Payment Gateway**, expediind un e-mail la ecom@maib.md.

În e-mailul dvs., asigurați-vă că includeți următoarele informații:
- Numele comerciantului
- Project ID
- Data și ora tranzacției cu erori
- Erori din fișierul cu log-uri