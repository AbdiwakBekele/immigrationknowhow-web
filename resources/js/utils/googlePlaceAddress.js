export function getAddressComponent(components, type) {
    return components.find((c) => Array.isArray(c.types) && c.types.includes(type));
}

export function componentLongText(component) {
    return component?.longText ?? component?.long_name ?? '';
}

export function componentShortText(component) {
    return component?.shortText ?? component?.short_name ?? '';
}

/**
 * Normalize a Google Places API (v1) place details object into address fields.
 */
export function parsePlaceToAddressFields(place) {
    const components = place.addressComponents ?? place.address_components ?? [];

    const streetNumber = componentLongText(getAddressComponent(components, 'street_number'));
    const route = componentLongText(getAddressComponent(components, 'route'));
    let addressLine1 = [streetNumber, route].filter(Boolean).join(' ').trim();

    const subpremise = componentLongText(getAddressComponent(components, 'subpremise'));
    const premise = componentLongText(getAddressComponent(components, 'premise'));
    const addressLine2 = [premise, subpremise].filter(Boolean).join(', ').trim();

    const locality = getAddressComponent(components, 'locality');
    const city =
        componentLongText(locality) ||
        componentLongText(getAddressComponent(components, 'sublocality')) ||
        componentLongText(getAddressComponent(components, 'administrative_area_level_2'));

    const admin1 = getAddressComponent(components, 'administrative_area_level_1');
    const countryComp = getAddressComponent(components, 'country');
    const countryIso = componentShortText(countryComp) || componentLongText(countryComp) || 'US';

    let state = componentShortText(admin1) || componentLongText(admin1);
    if (countryIso === 'US' && state.length > 2) {
        state = componentLongText(admin1);
    }

    const postal = componentLongText(getAddressComponent(components, 'postal_code'));

    if (!addressLine1) {
        addressLine1 =
            place.formattedAddress ||
            place.formatted_address ||
            '';
    }

    return {
        address_line_1: addressLine1,
        address_line_2: addressLine2,
        city,
        state,
        postal_code: postal,
        country: countryIso,
    };
}
