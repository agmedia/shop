<?php

// German storefront copy supplied by the client. Unlisted strings intentionally use the English storefront copy.
return array_replace_recursive(
    require __DIR__.'/../en/ui.php',
    [
        'front' => [
            'desktop' => [
                'account' => 'Mein Konto',
                'customer_reviews' => 'Kundenbewertungen',
                'newsletter' => [
                    'title' => 'Newsletter-Anmeldung für 10 % Rabatt auf den ersten Einkauf',
                    'subtitle' => 'Wir senden Ihnen gelegentlich Neuigkeiten und besondere Angebote.',
                    'placeholder' => 'Geben Sie Ihre E-Mail-Adresse ein',
                    'button' => 'ANMELDEN',
                    'consent' => 'Ich akzeptiere die Nutzungsbedingungen und die Verarbeitung meiner Daten für den Newsletter.',
                ],
                'benefits' => [
                    'shipping' => 'Kostenloser Versand ab 49,99 €',
                    'returns' => 'Rückgabe und Umtausch innerhalb von 14 Tagen',
                    'secure' => '100 % sichere Online-Zahlung',
                ],
                'footer' => [
                    'help' => 'HILFE',
                    'info' => 'INFORMATIONEN',
                    'support' => 'SUPPORT',
                    'webshop_queries' => 'Webshop-Anfragen',
                    'return_form' => 'Rückgabeformular',
                    'home' => 'STARTSEITE',
                    'work_hours' => 'Mo. – Fr. von 8:00 bis 16:00 Uhr',
                ],
                'cart' => 'Warenkorb',
            ],
        ],
        'account' => [
            'breadcrumb' => [
                'account' => 'Mein Konto',
                'home' => 'STARTSEITE',
            ],
            'dashboard' => [
                'page_title' => 'Mein Konto',
                'title' => 'Mein Konto',
                'cards' => [
                    'orders' => 'Bestellungen',
                    'user' => 'Benutzer',
                    'view_orders' => 'Alle Bestellungen anzeigen',
                ],
                'recent_orders' => [
                    'title' => 'Letzte Bestellungen',
                ],
                'subtitle_without_loyalty' => 'Persönliche Daten und Bestellübersicht',
            ],
            'orders' => [
                'table' => [
                    'total' => 'Gesamt',
                    'order' => 'BESTELLUNG',
                    'placed' => 'DATUM',
                    'status' => 'STATUS',
                    'actions' => 'Angebote',
                ],
                'page_title' => 'Meine Bestellungen',
                'title' => 'Meine Bestellungen',
                'subtitle' => 'Alle Bestellungen, die mit Ihrem Benutzerkonto verknüpft sind.',
                'empty' => 'Noch keine Bestellungen',
            ],
            'order_show' => [
                'table' => [
                    'total' => 'Gesamt',
                    'price' => 'Preis',
                ],
                'totals' => [
                    'labels' => [
                        'grand_total' => 'Gesamt',
                        'subtotal' => 'Zwischensumme',
                        'tax' => 'MwSt.',
                        'shipping' => 'Versand',
                    ],
                ],
                'placed_at' => 'DATUM',
                'status' => 'STATUS',
            ],
            'nav' => [
                'title' => 'KONTONAVIGATION',
                'dashboard' => 'Übersicht',
                'orders' => 'Bestellungen',
                'edit_account' => 'Konto bearbeiten',
                'logout' => 'Abmelden',
            ],
            'loyalty' => [
                'table' => [
                    'order' => 'BESTELLUNG',
                    'date' => 'DATUM',
                ],
            ],
            'profile' => [
                'page_title' => 'Profileinstellungen',
                'title' => 'Profileinstellungen',
                'subtitle' => 'Verwalten Sie Ihre persönlichen Daten, DSGVO-Einwilligungen und Adressen.',
                'personal_info' => 'Persönliche Daten',
                'preferences' => 'GDPR und Newsletter',
                'newsletter_opt_in' => 'Newsletter-Anmeldung',
                'gdpr_marketing' => 'GDPR-Marketingeinwilligung',
                'gdpr_personalization' => 'GDPR-Einwilligung zur Personalisierung',
            ],
            'fields' => [
                'display_name' => 'Anzeigename',
                'email' => 'E-Mail',
                'first_name' => 'Vorname',
                'last_name' => 'Nachname',
                'phone' => 'Telefon',
                'company' => 'Unternehmen',
                'oib' => 'OIB',
                'birthday' => 'Geburtsdatum',
                'gender' => 'Geschlecht',
                'vat_id' => 'USt-IdNr.',
                'address_line_1' => 'Adresse 1',
                'postal_code' => 'Postleitzahl',
                'city' => 'Stadt',
                'county' => 'GESPANSCHAFT',
                'select_county' => 'Gespanschaft auswählen',
                'country_code' => 'LAND',
            ],
            'gender' => [
                'female' => 'Weiblich',
                'male' => 'Männlich',
            ],
            'actions' => [
                'save_profile' => 'Profil speichern',
                'save_preferences' => 'Einstellungen speichern',
                'save_address' => ':typeadresse speichern',
            ],
            'address' => [
                'title' => ':typeadresse',
                'types' => [
                    'billing' => 'Rechnungs',
                    'shipping' => 'Liefer',
                ],
            ],
            'status' => [
                'address_updated' => ':typeadresse wurde aktualisiert.',
            ],
        ],
        'mobile' => [
            'menu' => [
                'my_account' => 'Mein Konto',
                'home' => 'STARTSEITE',
                'my_orders' => 'Meine Bestellungen',
                'profile_settings' => 'Profileinstellungen',
                'cart' => 'Warenkorb',
            ],
        ],
        'category' => [
            'total_suffix' => 'Gesamt',
            'fallback_name' => 'Kategorie',
        ],
        'cart' => [
            'table' => [
                'total' => 'Gesamt',
                'price' => 'Preis',
                'actions' => 'Aktionen',
                'product' => 'Produkt',
                'quantity' => 'Menge',
                'save' => 'Speichern',
            ],
            'summary' => [
                'total' => 'Gesamt',
                'title' => 'Zusammenfassung',
                'items' => 'Artikel',
                'subtotal' => 'Zwischensumme',
                'tax' => 'MwSt.',
                'shipping' => 'Versand',
            ],
            'page_title' => 'Warenkorb',
            'title' => 'Warenkorb',
            'subtitle' => 'Überprüfen Sie Ihre Produkte vor dem Abschluss des Kaufs.',
            'empty' => 'Ihr Warenkorb ist derzeit leer.',
            'actions' => [
                'continue' => 'Einkauf fortsetzen',
                'checkout' => 'Zur Kasse',
                'apply_coupon' => 'Gutschein anwenden',
            ],
            'modal' => [
                'quantity' => 'MENGE',
            ],
            'coupon' => [
                'toggle_open' => 'ICH HABE EINEN GUTSCHEIN',
                'toggle_close' => 'GUTSCHEIN SCHLIESSEN',
                'label' => 'GUTSCHEIN',
                'placeholder' => 'Gutscheincode eingeben',
            ],
        ],
        'checkout' => [
            'labels' => [
                'total' => 'Gesamt',
                'sku' => 'Artikelnummer',
                'product' => 'Produkt',
                'items' => 'Artikel',
                'subtotal' => 'Zwischensumme',
                'tax' => 'MwSt.',
                'shipping' => 'Versand',
                'shipping_method' => 'Versandart',
                'payment_method' => 'Zahlungsart',
            ],
            'success' => [
                'grand_total' => 'Gesamt',
                'status' => 'STATUS',
                'continue_shopping' => 'Einkauf fortsetzen',
                'summary' => 'Bestellübersicht',
            ],
            'login' => [
                'email' => 'E-Mail',
            ],
            'sections' => [
                'billing' => 'Rechnungsadresse',
                'shipping' => 'Lieferadresse',
                'basic_information' => 'Grundlegende Daten',
                'shipping_payment' => 'Versand und Zahlung',
                'customer' => 'Kunde',
            ],
            'page_title' => 'Kasse',
            'title' => 'Kasse',
            'subtitle' => 'Geben Sie Ihre Daten ein und schließen Sie Ihre Bestellung ab.',
            'options' => [
                'r1_invoice' => 'Ich möchte eine R1-Rechnung',
                'ship_to_different_address' => 'An eine andere Adresse liefern',
                'accept_terms' => 'Ich akzeptiere die Einkaufsbedingungen.',
                'newsletter_opt_in' => 'Ich möchte den Newsletter mit Angeboten und Neuigkeiten erhalten',
            ],
            'summary_title' => 'Bestellübersicht',
            'actions' => [
                'place_order' => 'Bestellen',
            ],
            'shipping_methods' => [
                'standard' => 'Versand mit DPD Kroatien – Kostenloser Versand für Bestellungen über 50 €',
                'pickup' => 'Abholung im Geschäft (Hrupine 19, 40323, Prelog, Kroatien)',
            ],
            'payment_methods' => [
                'cod' => 'Zahlung bei Lieferung',
                'corvuspay' => 'Zahlung mit Kredit- und Debitkarten – CorvusPay',
                'bank' => 'Banktransaktion / Banküberweisung / QR-Code',
            ],
            'fields' => [
                'order_note' => 'Anmerkung zur Bestellung',
            ],
        ],
        'shop' => [
            'filters' => [
                'price' => 'Preis',
                'category' => 'Kategorie',
                'price_from' => 'VON',
                'price_to' => 'BIS',
                'promotion_only' => 'Nur Produkte im Angebot anzeigen',
                'promotion_only_hint' => 'Mit Aktionspreis',
                'reset' => 'Zurücksetzen',
                'default' => 'Standard-Sortierung',
                'newest' => 'Neueste zuerst',
                'oldest' => 'Älteste zuerst',
                'price_low' => 'Preis aufsteigend',
                'price_high' => 'Preis absteigend',
                'stock_high' => 'Verfügbarkeit',
            ],
        ],
        'product' => [
            'attribute_groups' => [
                'composition' => 'Materialzusammensetzung',
            ],
            'sku' => 'Artikelnummer',
            'color_variants' => 'Farbvarianten',
            'comments_title' => 'Bewertungen',
            'comment_form' => [
                'toggle' => 'BEWERTUNG HINZUFÜGEN',
                'email' => 'E-Mail',
                'name' => 'Vorname',
            ],
            'related' => 'Ähnliche Produkte',
            'recently_viewed' => 'Zuletzt angesehen',
            'comments_empty' => 'Noch keine Bewertungen für diesen Artikel',
        ],
        'auth' => [
            'fields' => [
                'email' => 'E-Mail',
                'first_name' => 'Vorname',
                'last_name' => 'Nachname',
            ],
        ],
    ]
);
