```markdown
# Compat: PHP 8.3 & WooCommerce 9.x

Cel:
- Przygotować wtyczkę do działania pod PHP 8.3 i WooCommerce 9.x.
- Usunąć/przeimplementować przestarzałe funkcje, naprawić dynamic properties oraz błędy związane z dostępem do offsetów tablic.

Co zawiera ten branch:
- Automatyczne transformacje kodu poprzez Rector (zestawy PHP 8.x, CodeQuality, DeadCode).
- Zastosowane poprawki stylu przez PHP-CS-Fixer.
- Skrypt narzędziowy, który konserwatywnie deklaruje dynamiczne właściwości klas (wymaga przeglądu).
- Skrypt (apply-fixes.sh) automatyzujący uruchomienie narzędzi.

Instrukcja testowania:
1. Uruchom workflow ręcznie: Actions → Auto: PHP 8.3 & WooCommerce 9.x compatibility fixes → Run workflow.
2. Workflow utworzy branch `fix/php8.3-woocommerce-9-compat` i PR jeżeli wykryje zmiany.
3. Przejrzyj PR: sprawdź deklaracje właściwości, poprawność typów oraz miejsca używające API WooCommerce.

Uwagi:
- dynamic_property_fixer stosuje heurystykę — PR należy dokładnie przeglądnąć i uzupełnić typowanie.
- Jeżeli wolisz, można ograniczyć zakres Rector do konkretnych katalogów (np. src/ lub includes/).
```