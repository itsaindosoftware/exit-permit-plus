import PriceSupplierForm from './Form';

export default function Create({ defaultIsActive = false }) {
    return (
        <PriceSupplierForm
            mode="create"
            defaultIsActive={defaultIsActive}
            submitRouteName="price-suppliers.store"
        />
    );
}