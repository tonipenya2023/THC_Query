<?php
// tests/ExpeditionFilterTest.php - Prueba básica
require_once __DIR__ . '/../src/bootstrap.php';

use THC\Domain\Expeditions\ExpeditionFilter;

// Simular request
$request = [
    'username' => 'TheBubb',
    'reserve' => 'Whitehart',
    'limit' => '100',
    'has_deaths' => '1'
];

$filter = ExpeditionFilter::fromRequest($request);

echo "Filter created:\n";
echo "Username: " . ($filter->username ?? 'null') . "\n";
echo "Reserve: " . ($filter->reserve ?? 'null') . "\n";
echo "Limit: " . $filter->limit . "\n";
echo "Has deaths: " . ($filter->hasDeaths ? 'true' : 'false') . "\n";
echo "Order by: " . $filter->orderBy . "\n";

$conditions = $filter->buildWhereConditions('gpt');
echo "Conditions: " . implode(' AND ', $conditions[0]) . "\n";

echo "Test passed!\n";