import './search-debug-tree-diagram.scss';
import register from 'ShopUi/app/registry';

export default register(
    'search-debug-tree-diagram',
    () =>
        import(
            /* webpackMode: "lazy" */
            /* webpackChunkName: "search-debug-tree-diagram" */
            './search-debug-tree-diagram'
        ),
);
