<?php

namespace App\Enums;

/**
 * Allowed values for store_categories.store_category_variant_key.
 *
 * Every value MUST match a key of STORE_VARIANTS in the dashboard's
 * store-type-config.js. The DB column stays a plain string(45) on purpose,
 * so adding a new variant only needs a new case here (and in the JS config),
 * with no migration.
 */
enum StoreCategoryVariant: string
{
    case General = 'general';
    case Grocery = 'grocery';
    case Produce = 'produce';
    case Butcher = 'butcher';
    case Fish = 'fish';
    case Bakery = 'bakery';
    case Sweets = 'sweets';
    case Spices = 'spices';
    case Menswear = 'menswear';
    case Womenswear = 'womenswear';
    case Kidswear = 'kidswear';
    case Shoes = 'shoes';
    case Accessories = 'accessories';
    case Tailoring = 'tailoring';
    case Pharmacy = 'pharmacy';
    case Medical = 'medical';
    case Optics = 'optics';
    case Furniture = 'furniture';
    case Furnishings = 'furnishings';
    case Household = 'household';
    case Appliances = 'appliances';
    case Cleaning = 'cleaning';
    case Plumbing = 'plumbing';
    case Clinic = 'clinic';
    case Mobile = 'mobile';
    case Computers = 'computers';
    case Electronics = 'electronics';
    case Solar = 'solar';
    case Repair = 'repair';
    case BuildingMat = 'buildingmat';
    case IronAlu = 'ironalu';
    case PaintsDecor = 'paintsdecor';
    case Wood = 'wood';
    case Tiles = 'tiles';
    case Stationery = 'stationery';
    case Toys = 'toys';
    case ArtSupplies = 'artsupplies';
    case SalonMen = 'salonmen';
    case SalonWomen = 'salonwomen';
    case Laundry = 'laundry';
    case Photography = 'photography';
    case AutoParts = 'autoparts';
    case Garage = 'garage';
    case Tires = 'tires';
    case Vet = 'vet';
    case Feed = 'feed';
    case AgriTools = 'agritools';
}