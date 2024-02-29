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
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\OAuth\OAuthTokenManager;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TIProvider implements InfoProviderInterface
{

    private const OAUTH_APP_NAME = 'ip_ti_oauth';

    //Sandbox:'https://sandbox-api.digikey.com'; (you need to change it in knpu/oauth2-client-bundle config too)
    private const BASE_URI = 'https://transact.ti.com';

    private const VENDOR_NAME = 'TI';

    private readonly HttpClientInterface $tiClient;


    public function __construct(HttpClientInterface $httpClient, private readonly OAuthTokenManager $authTokenManager,
        private readonly string $currency, private readonly string $clientId,
        private readonly string $language, private readonly string $country, private readonly string $secret)
    {
        //Create the HTTP client with some default options
        $this->tiClient = $httpClient->withOptions([
            "base_uri" => self::BASE_URI,
            "headers" => [
                "accept" => "application/json",
            ]
        ]);
    }


    /**
     * Gets the latest OAuth token for the Octopart API, or creates a new one if none is available
     * @return string
     */
    private function getToken(): string
    {
        //Check if we already have a token saved for this app, otherwise we have to retrieve one via OAuth
        if (!$this->authTokenManager->hasToken(self::OAUTH_APP_NAME)) {
            $this->authTokenManager->retrieveClientCredentialsToken(self::OAUTH_APP_NAME);
        }

        $tmp = $this->authTokenManager->getAlwaysValidTokenString(self::OAUTH_APP_NAME);
        if ($tmp === null) {
            throw new \RuntimeException('Could not retrieve OAuth token for TI');
        }

        return $tmp;
    }


    public function getProviderInfo(): array
    {
        return [
            'name' => 'Texas Instruments',
            'description' => 'This provider uses the TI API to search for parts.',
            'url' => 'https://www.ti.com/',
            'oauth_app_name' => self::OAUTH_APP_NAME,
            'disabled_help' => 'Set the PROVIDER_TI_CLIENT_ID and PROVIDER_TI_SECRET env option and connect OAuth to enable.'
        ];
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::FOOTPRINT,
//            ProviderCapabilities::PICTURE,
            ProviderCapabilities::DATASHEET,
            ProviderCapabilities::PRICE,
        ];
    }

    public function getProviderKey(): string
    {
        return 'ti';
    }

    public function isActive(): bool
    {
        //The client ID has to be set and a token has to be available (user clicked connect)
	//return !empty($this->clientId) && $this->authTokenManager->hasToken(self::OAUTH_APP_NAME);
	return !empty($this->clientId) && !empty($this->secret);
    }

    public function searchByKeyword(string $keyword): array
    {
        $request = [
            'GenericProductIdentifier' => strtoupper($keyword), // API is case sensitive and afaik all TI product IDs are uppercase
            'Size' => 50,
            'Page' => 0,
        ];

        $options = (new HttpOptions())
            ->setAuthBearer($this->getToken())
        ;

        $response = $this->tiClient->request('GET', '/v1/products', array_merge(['query' => $request,], $options->toArray()));
	if ($response->getStatusCode() >= 300)
		return []; // TODO error handling

        $response_array = $response->toArray();


        $result = [];
        $products = $response_array['Content'];
        foreach ($products as $product) {
            $result[] = new SearchResultDTO(
                provider_key: $this->getProviderKey(),
                provider_id: $product['Identifier'],
                name: $product['GenericProductIdentifier'],
                description: $product['Description'],
                category: $product['ProductFamilyDescription'],
                manufacturer: "TI",
                mpn: $product['Identifier'],
                preview_image_url: $product['PrimaryPhoto'] ?? null,
                manufacturing_status: $this->productStatusToManufacturingStatus($product['LifeCycleStatus'], $product["Obsolete"], $product["LifetimeBuy"]),
                provider_url: $product['Url'],
            );
        }

        return $result;
    }

    public function getDetails(string $id): PartDetailDTO
    {
	// TODO we might use products-extended API in the future to retrieve additional parameters
        $response = $this->tiClient->request('GET', '/v1/products/' . urlencode($id), [
            'auth_bearer' => $this->authTokenManager->getAlwaysValidTokenString(self::OAUTH_APP_NAME)
        ]);

        $product = $response->toArray();

        $footprint = null;
        // $parameters = $this->parametersToDTOs($product['Parameters'] ?? [], $footprint);
	// TODO possible with additional query on subpath "/parametrics"

	//$media = $this->mediaToDTOs($product['MediaLinks']);
        $file = new FileDTO(url: $product['DatasheetUrl'], name: "Datasheet");

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $product['Identifier'],
            name: $product['GenericProductIdentifier'],
            description: $product['Description'],
            category: $product['ProductFamilyDescription'],
            manufacturer: "TI",
            mpn: $product['Identifier'],
            preview_image_url: $product['PrimaryPhoto'] ?? null,
            manufacturing_status: $this->productStatusToManufacturingStatus($product['LifeCycleStatus'], $product["Obsolete"], $product["LifetimeBuy"]),
            provider_url: $product['Url'],
            footprint: $product['IndustryPackageType'], // TODO maybe append pin count ($product['Pin'])automagically?
            datasheets: [$file],
            images: $product['images'] ?? null,
//            parameters: $parameters,
            vendor_infos: $this->pricingToDTOs($product['Price'] ?? [], $product['Identifier'], $product['Url']),
        );
    }

    /**
     * Converts the lifecycle status from the TI API to the manufacturing status used in Part-DB
     * @param  string|null  $lifecycle
     * @param  bool $obsolete
     * @param  bool $lifetimebuy
     * @return ManufacturingStatus|null
     */
    private function productStatusToManufacturingStatus(?string $lifecycle, bool $obsolete, bool $lifetimebuy): ?ManufacturingStatus
    {
        if ($lifetimebuy){
            return ManufacturingStatus::EOL;
        }

        if ($obsolete){
            return ManufacturingStatus::DISCONTINUED;
        }

        return match ($lifecycle) {
            null => null,
            'ACTIVE' => ManufacturingStatus::ACTIVE,
            'LAST TIME BUY' => ManufacturingStatus::DISCONTINUED,
            'OBSOLETE' => ManufacturingStatus::EOL,
            'NOT RECOMMENDED FOR NEW DESIGNS' => ManufacturingStatus::NRFND,
            'PREVIEW' => ManufacturingStatus::ANNOUNCED,
            default => ManufacturingStatus::NOT_SET,
        };
// TI doesn't seem to use NRND anymore "because it was causing concern for many customers who were afraid that TI would no longer produce certain products", so I could not find any real world reponse to test this.
// PREVIEW, although displayed on their webpage, is not reflected in the API unfortunately. I reached out to their support to fix it.
    }

    /**
     * TI orchestrated API delivers parameters, but it takes extremely long to respond, so it is currently left unimplemented
     * @param  array  $parameters
     * @param string|null  $footprint_name You can pass a variable by reference, where the name of the footprint will be stored
     * @return ParameterDTO[]
     */
    private function parametersToDTOs(array $parameters, string|null &$footprint_name = null): array
    {
        $results = [];

        $footprint_name = null;

        foreach ($parameters as $parameter) {
            $results[] = ParameterDTO::parseValueIncludingUnit($parameter['Parameter'], $parameter['Value']);
        }

        return $results;
    }

    /**
     * Converts the pricing (Price field) from the TI API to a PurchaseInfoDTO
     * @param  array  $price_breaks
     * @param  string  $order_number
     * @param  string  $product_url
     * @return PurchaseInfoDTO[]
     */
    private function pricingToDTOs(array $price_breaks, string $order_number, string $product_url): array
    {
        $prices = [];

        $price_break = $price_breaks;
	{
            $prices[] = new PriceDTO(minimum_discount_amount: $price_break['Quantity'], price: (string) $price_break['Value'], currency_iso_code: $this->currency);
        }

        return [
            new PurchaseInfoDTO(distributor_name: self::VENDOR_NAME, order_number: $order_number, prices: $prices, product_url: $product_url)
        ];
    }

    /**
     * TI API TODO - image link not available
     * @param  array  $media_links
     * @return FileDTO[][]
     * @phpstan-return array<string, FileDTO[]>
     */
    private function mediaToDTOs(array $media_links): array
    {
        $datasheets = [];
        $images = [];

        foreach ($media_links as $media_link) {
            $file = new FileDTO(url: $media_link['Url'], name: $media_link['Title']);

            switch ($media_link['MediaType']) {
                case 'Datasheets':
                    $datasheets[] = $file;
                    break;
                case 'Product Photos':
                    $images[] = $file;
                    break;
            }
        }

        return [
            'datasheets' => $datasheets,
            'images' => $images,
        ];
    }

}
