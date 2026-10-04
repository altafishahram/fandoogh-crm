# Iran administrative reference snapshot

This is a **1404 snapshot**, retrieved on 2026-10-03; it is not a claim that the government has verified the current 1405 divisions.

Source: [Hameds/IranCountryDivisions](https://github.com/Hameds/IranCountryDivisions/tree/68687cf96cc1852d5d38c7283353c80829331758), revision `68687cf96cc1852d5d38c7283353c80829331758`. The source author identifies its upstream as the Statistical Centre of Iran annual geography workbook, retained as `source/1404/geo_1404.xlsx`. The government site could not be accessed during this implementation; independent comparison against a newly obtained government publication remains possible.

Extracted from `data/1404/iran.json`: type 1 provinces, type 2 counties, type 5 cities. Cities are attached to their county through their type 3 district parent. Type 7 urban zones are excluded from the city picker. Extraction validated every county/province and city/district/county chain and counts **31 provinces, 484 counties, 1,481 cities**. No neighborhoods are invented; neighborhood remains text.

The numeric reference IDs are stable for this pinned snapshot. Source record IDs may change between source versions. Future imports must match province/county code paths and national city codes, preserve application IDs and review moved/deleted divisions; never replace the snapshot in place with a new source's IDs.

Reference tables are seeded explicitly using `IranLocationsSeeder`; it creates no users, credentials or publications. Existing text and nullable location IDs remain intact. An ambiguous legacy location must be corrected explicitly; the agency default is not applied retroactively to old records.

The source is distributed under MIT. Copyright and permission are retained in [IRAN_DATA_LICENSE.txt](IRAN_DATA_LICENSE.txt).
