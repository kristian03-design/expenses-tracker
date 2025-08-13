<?php

class Currency {
    private $config;
    private $rates = [];
    private $lastUpdate = 0;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config/app.php';
    }
    
    /**
     * Format amount according to currency settings
     */
    public function format($amount, $currency = null) {
        if ($currency === null) {
            $currency = $this->config['currency']['default'];
        }
        
        if (!isset($this->config['currency']['supported'][$currency])) {
            $currency = $this->config['currency']['default'];
        }
        
        $settings = $this->config['currency']['supported'][$currency];
        $formatted = number_format(
            $amount, 
            $settings['decimals'], 
            $settings['decimal_separator'], 
            $settings['thousands_separator']
        );
        
        if ($settings['position'] === 'before') {
            return $settings['symbol'] . $formatted;
        } else {
            return $formatted . ' ' . $settings['symbol'];
        }
    }
    
    /**
     * Convert amount from one currency to another
     */
    public function convert($amount, $fromCurrency, $toCurrency) {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }
        
        $rates = $this->getExchangeRates();
        
        if (!isset($rates[$fromCurrency]) || !isset($rates[$toCurrency])) {
            return $amount; // Return original amount if conversion not possible
        }
        
        // Convert to base currency (USD) first, then to target currency
        $usdAmount = $amount / $rates[$fromCurrency];
        return $usdAmount * $rates[$toCurrency];
    }
    
    /**
     * Get exchange rates for all supported currencies
     */
    public function getExchangeRates() {
        $now = time();
        
        // Check if we need to update rates
        if ($now - $this->lastUpdate > $this->config['currency']['exchange_rate_api']['update_interval']) {
            $this->updateExchangeRates();
        }
        
        return $this->rates;
    }
    
    /**
     * Update exchange rates from API or use fallback rates
     */
    private function updateExchangeRates() {
        $apiConfig = $this->config['currency']['exchange_rate_api'];
        
        if ($apiConfig['enabled']) {
            $rates = $this->fetchExchangeRates();
            if ($rates) {
                $this->rates = $rates;
                $this->lastUpdate = time();
                return;
            }
        }
        
        // Use fallback rates if API fails or is disabled
        $this->rates = $apiConfig['fallback_rates'];
        $this->lastUpdate = time();
    }
    
    /**
     * Fetch exchange rates from external API
     */
    private function fetchExchangeRates() {
        $apiConfig = $this->config['currency']['exchange_rate_api'];
        $url = $apiConfig['url'] . 'USD';
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'ExpenseTracker/1.0'
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            if ($response === false) {
                return false;
            }
            
            $data = json_decode($response, true);
            if (!$data || !isset($data['rates'])) {
                return false;
            }
            
            return $data['rates'];
            
        } catch (Exception $e) {
            error_log("Failed to fetch exchange rates: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get supported currencies list
     */
    public function getSupportedCurrencies() {
        return array_keys($this->config['currency']['supported']);
    }
    
    /**
     * Get currency details
     */
    public function getCurrencyDetails($currency) {
        return $this->config['currency']['supported'][$currency] ?? null;
    }
    
    /**
     * Get default currency
     */
    public function getDefaultCurrency() {
        return $this->config['currency']['default'];
    }
    
    /**
     * Validate if currency is supported
     */
    public function isSupported($currency) {
        return isset($this->config['currency']['supported'][$currency]);
    }
    
    /**
     * Parse amount from formatted string
     */
    public function parseAmount($formattedAmount, $currency = null) {
        if ($currency === null) {
            $currency = $this->config['currency']['default'];
        }
        
        if (!isset($this->config['currency']['supported'][$currency])) {
            return 0;
        }
        
        $settings = $this->config['currency']['supported'][$currency];
        
        // Remove currency symbol and spaces
        $cleanAmount = str_replace($settings['symbol'], '', $formattedAmount);
        $cleanAmount = trim($cleanAmount);
        
        // Replace thousands separator with empty string
        $cleanAmount = str_replace($settings['thousands_separator'], '', $cleanAmount);
        
        // Replace decimal separator with dot for PHP number parsing
        $cleanAmount = str_replace($settings['decimal_separator'], '.', $cleanAmount);
        
        return (float) $cleanAmount;
    }
    
    /**
     * Get currency symbol
     */
    public function getSymbol($currency = null) {
        if ($currency === null) {
            $currency = $this->config['currency']['default'];
        }
        
        return $this->config['currency']['supported'][$currency]['symbol'] ?? '$';
    }
    
    /**
     * Get currency position (before/after)
     */
    public function getPosition($currency = null) {
        if ($currency === null) {
            $currency = $this->config['currency']['default'];
        }
        
        return $this->config['currency']['supported'][$currency]['position'] ?? 'before';
    }
    
    /**
     * Get number of decimal places for currency
     */
    public function getDecimals($currency = null) {
        if ($currency === null) {
            $currency = $this->config['currency']['default'];
        }
        
        return $this->config['currency']['supported'][$currency]['decimals'] ?? 2;
    }
}
