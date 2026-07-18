import './search-debug-product-info.scss';
import register from 'ShopUi/app/registry';

export default register(
    'search-debug-product-info',
    () =>
        import(
            /* webpackMode: "lazy" */
            /* webpackChunkName: "search-debug-product-info" */
            './search-debug-product-info'
        ),
);
