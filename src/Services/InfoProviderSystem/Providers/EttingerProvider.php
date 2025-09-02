<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);


namespace App\Services\InfoProviderSystem\Providers;

use App\Entity\Parts\ManufacturingStatus;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Settings\InfoProviderSystem\EttingerSettings;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EttingerProvider implements InfoProviderInterface
{

    private const BASE_URI = 'https://api.ettinger.de';

    private const VENDOR_NAME = 'Ettinger';

    private readonly HttpClientInterface $ettingerClient;

    public function __construct(HttpClientInterface $httpClient,
        private readonly EttingerSettings $settings)
    {
        //Create the HTTP client with some default options
        $this->ettingerClient = $httpClient->withOptions([
            "base_uri" => self::BASE_URI,
            "headers" => [
                "Authorization" => $this->settings->apiKey,
                "X-Article-Price" => true,
                "X-Article-Technical" => true,
                "X-Article-Availability" => true,
                "X-Article-Metadata" => true,
            ]
        ]);
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Ettinger',
            'description' => 'This provider uses the Ettinger API to search for parts.',
            'url' => 'https://www.ettinger.de/',
            'disabled_help' => 'Enable the Ettinger info provider in the system settings.',
            'settings_class' => EttingerSettings::class,
        ];
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::PRICE,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::DATASHEET,
        ];
    }

    public function getProviderKey(): string
    {
        return 'ettinger';
    }

    public function isActive(): bool
    {
        return !empty($this->settings->apiKey);
    }

    public function searchByKeyword(string $keyword): array
    {
        $response = $this->ettingerClient->request('GET', '/v1/product?search=' . urlencode($keyword) . '&limit=' . $this->settings->searchLimit);
        if ($response->getStatusCode() == 404)
            return [];

        $products = $response->toArray();

        $result = [];

        foreach ($products as $product){
            $result[] = new SearchResultDTO(
                provider_key: $this->getProviderKey(),
                // $product['matchcode'] equals the part no. w/o points
                provider_id: (string)$product['id'],
                name: $product['part_no'],
                description: $product['description1_' . $this->settings->locale] .' '. $product['description2_' . $this->settings->locale],
                category: $this->_category,
                manufacturer: "Ettinger",
                mpn: $product['part_no'], // not quite correct considering they also supply 3rd party products
                preview_image_url: $product['image_url'] ?? null, // not (yet) available
                manufacturing_status: $this->productStatusToManufacturingStatus($product['price_available']), // wild guess; alternatively just use NOT_SET
                provider_url: str_replace('/de/', '/'.$this->settings->locale.'/', $product['webshop_url'] ?? ''),
            );
	}

        return $result;
    }

    public function getDetails(string $id): PartDetailDTO
    {
        $response = $this->ettingerClient->request('GET', '/v1/product?id=' . urlencode($id));

        $product = $response->toArray()[0];

        $productUrl = str_replace('/de/', '/'.$this->settings->locale.'/', $product['webshop_url'] ?? '');

        $availablity = $product['availability'];
        
        // json: {
            // "part_no": "012.34.56",
            // "status_code": 1, (1=in stock, 0=out of stock...?)
            // "quantity": 1900, (quantity in stock)
            // "next_receipt": 999, (default value)
            // "lead_time": 10 (days)
        // },

        $params = $product['technical_details_'.$this->settings->locale];

        //json: [
            // {
                // "Nr": 1,
                // "Materialgruppe": "Stahl rostfrei"
            // },
            // {
                // "Nr": 2,
                // "Material": "Stahl rostfrei A4"
            // },
            // {
                // "Nr": 3,
                // "Oberfläche": "blank"
            // },
            // ...
         // ]

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: (string)$product['id'],
            name: $product['part_no'],
            description: $product['description1_' . $this->settings->locale] . ' ' . $product['description2_' . $this->settings->locale],
            category: $this->settings->category,
            manufacturer: "Ettinger",
            mpn: $product['part_no'],
            preview_image_url: $product['image_url'] ?? null, // not yet available
            manufacturing_status: $this->productStatusToManufacturingStatus($product['price_available']),
            provider_url: $productUrl,
            footprint: null,
            mass: $this->convertWeight(floatval($product['weight_per_piece']), $product['weight_unit']),
            datasheets: null,    // TODO info is retrievable with X-Article-Metadata but not yet populated
            images: null,        // TODO info is retrievable with X-Article-Metadata but not yet populated
            parameters: null,    // TODO info is retrievable with X-Article-Technical
            vendor_infos: $this->pricingToDTOs($product['prices'] ?? [], $product['part_no'], $productUrl),
        );
    }

    /**
     * Converts the product status from the Ettinger API to the manufacturing status used in Part-DB
     * @param  string|null  $dk_status
     * @return ManufacturingStatus|null
     */
    private function productStatusToManufacturingStatus(bool $dk_status): ?ManufacturingStatus
    {
        return match ($dk_status) {
            null => null,
            true => ManufacturingStatus::ACTIVE,
            default => ManufacturingStatus::NOT_SET,
        };
    }

    /**
     * Converts the pricing (StandardPricing field) from the Ettinger API to an array of PurchaseInfoDTOs
     * @param  array  $price_breaks
     * @param  string  $order_number
     * @param  string  $product_url
     * @return PurchaseInfoDTO[]
     */
    private function pricingToDTOs(array $price_breaks, string $order_number, string $product_url): array
    {
        $prices = [];

        foreach ($price_breaks as $price_break) {
            $prices[] = new PriceDTO(minimum_discount_amount: $price_break['quantity'], price: (string) $price_break['price'], currency_iso_code: "EUR", includes_tax: false, price_related_quantity: $price_break['price_unit']);
        }

        return [
            new PurchaseInfoDTO(distributor_name: self::VENDOR_NAME, order_number: $order_number, prices: $prices, product_url: $product_url)
        ];
    }

    private function convertWeight(float $weight, string $unit): float
    {
        switch ($unit)
        {
            case 'milligramm';
            case 'milligram';
            case 'mg':
                return $weight/1000;
            case 'gramm';
            case 'gram';
            case 'g':
                return $weight;
            case 'kilogramm';
            case 'kilogram';
            case 'kg':
            return $weight*1000;
            case 'tonnen';
            case 'tonnes';
            case 'tonne';
            case 'tons';
            case 'ton';
            case 't';
                return $weight*1000000;
            default:
                return 0;
        }
    }
}
