import PriceSupplierForm from './Form';

export default function Edit({ priceSupplier }) {
    return (
        <PriceSupplierForm
            mode="edit"
            priceSupplier={priceSupplier}
            submitRouteName="price-suppliers.update"
        />
    );
}