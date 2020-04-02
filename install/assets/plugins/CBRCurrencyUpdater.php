//<?php
/**
 * CBR Currency Updater
 *
 * Updates currencies from CBR feed
 *
 * @category    plugin
 * @version     0.1.0
 * @author      mnoskov
 * @internal    @events OnWebPageInit,OnManagerBeforeDefaultCurrencyChange
 * @internal    @modx_category Commerce
 * @internal    @installset base
*/

if (!defined('COMMERCE_INITIALIZED')) {
    return;
}

switch ($modx->event->name) {
    case 'OnWebPageInit': {
        if ($modx->documentIdentifier != $modx->getConfig('site_start')) {
            return;
        }

        ci()->cache->getOrCreate('cbrupdater', function() use ($modx) {
            $currencies  = ci()->currency->getCurrencies();
            $defaultCode = ci()->currency->getDefaultCurrencyCode();
            $default     = $currencies[$defaultCode];

            $xml = new \SimpleXMLElement('http://www.cbr.ru/scripts/XML_daily.asp', 0, true);

            $values = [];

            if (isset($currencies['RUB'])) {
                $values['RUB'] = 1;
            }

            foreach ($xml->Valute as $item) {
                $code = (string) $item->CharCode;

                if (isset($currencies[$code])) {
                    $values[$code] = str_replace(',', '.', $item->Value) / $item->Nominal;
                }
            }

            if ($defaultCode != 'RUB') {
                foreach ($values as $code => $value) {
                    if ($code != $defaultCode) {
                        $values[$code] = $value / $values[$defaultCode];
                    }
                }

                $values[$defaultCode] = 1;
            }

            $updated = false;
            $table = $modx->getFullTablename('commerce_currency');

            foreach ($values as $code => $value) {
                $value = substr($value, 0, 7);

                if ($currencies[$code]['value'] != $value) {
                    $modx->db->update(['value' => $modx->db->escape($value)], $table, "`code` = '" . $modx->db->escape($code) . "'");
                    $updated = true;
                }
            }

            if ($updated) {
                $modx->clearCache('full');
            }

            return true;
        }, ['seconds' => 3600]);

        break;
    }

    case 'OnManagerBeforeDefaultCurrencyChange': {
        ci()->cache->forget('cbrupdater');
        break;
    }
}
