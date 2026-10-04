import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final marketplaceRepositoryProvider = Provider<MarketplaceRepository>(
  (ref) => MarketplaceRepository(ref.watch(apiClientProvider)),
);

/// Listing deliberately does not retain the private property response/map.
final class Listing {
  Listing.fromJson(Map<String, dynamic> json)
    : id = (json['id'] as num).toInt(),
      title = '${json['title'] ?? ''}',
      neighborhood = '${json['neighborhood'] ?? ''}',
      propertyType = '${json['property_type'] ?? ''}',
      transactionType = '${json['transaction_type'] ?? ''}',
      area = '${json['area_sqm'] ?? ''}',
      bedrooms = '${json['bedrooms'] ?? ''}',
      yearBuilt = '${json['year_built'] ?? ''}',
      salePrice = '${json['sale_price'] ?? ''}',
      deposit = '${json['deposit_amount'] ?? ''}',
      rent = '${json['monthly_rent'] ?? ''}',
      description = '${json['description'] ?? ''}',
      cover = json['cover_image_url'] as String?,
      agencyName = '${(json['agency'] as Map?)?['name'] ?? ''}',
      agencyPhone = '${(json['agency'] as Map?)?['phone'] ?? ''}',
      specifications = Map<String, dynamic>.fromEntries(
        Map<String, dynamic>.from(
          json['specifications'] is Map ? json['specifications'] as Map : {},
        ).entries.where((entry) => publicSpecificationKeys.contains(entry.key)),
      ),
      images = (json['images'] is List ? json['images'] as List : [])
          .whereType<Map>()
          .map((e) => '${e['url']}')
          .toList();
  final int id;
  final String title,
      neighborhood,
      propertyType,
      transactionType,
      area,
      bedrooms,
      yearBuilt,
      salePrice,
      deposit,
      rent,
      description,
      agencyName,
      agencyPhone;
  final String? cover;
  final Map<String, dynamic> specifications;
  final List<String> images;
}

const publicSpecificationKeys = {
  'bathrooms',
  'floor_number',
  'total_floors',
  'parking_spaces',
  'has_storage_room',
  'has_elevator',
  'has_balcony',
  'units_per_floor',
  'master_bedrooms',
  'toilet_types',
  'cabinet_type',
  'heating_type',
  'cooling_type',
  'flooring_type',
  'renovation_status',
  'building_orientation',
  'deed_type',
  'has_loan',
  'is_exchangeable',
  'has_pool',
  'has_jacuzzi',
  'has_sauna',
  'building_type',
  'structure_type',
  'has_water',
  'has_electricity',
  'has_gas',
  'land_area',
  'building_area',
  'can_aggregate',
  'land_frontage',
  'is_convertible',
  'minimum_deposit',
};

final class MarketplaceRepository extends ApiRepository {
  const MarketplaceRepository(super.client);
  Future<PagedResult<Listing>> listings(
    String audience,
    Map<String, Object?> filters,
    int page,
  ) => getPage(
    '/marketplace/listings',
    query: {...filters, 'audience': audience, 'page': page},
    decode: Listing.fromJson,
  );
  Future<Listing> listing(int id, String audience) async => Listing.fromJson(
    await getOne('/marketplace/listings/$id?audience=$audience'),
  );
  Future<Map<String, dynamic>> publication(int property) =>
      getOne('/properties/$property/publication');
  Future<Map<String, dynamic>> savePublication(
    int property,
    Map<String, Object?> values,
  ) async {
    final response = await client.dio.put<Map<String, dynamic>>(
      '/properties/$property/publication',
      data: values,
    );
    return ApiRepository.unwrapData(response.data);
  }
}
