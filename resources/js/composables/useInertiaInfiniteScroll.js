import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Append paginated Inertia props as the user scrolls (intersection observer sentinel).
 */
export function useInertiaInfiniteScroll(getPaginator, options) {
    const {
        getUrl,
        buildQuery = () => ({}),
        pageParam = 'page',
        only = null,
        rootMargin = '320px 0px',
    } = options;

    const displayedItems = ref([]);
    const currentPage = ref(1);
    const lastPage = ref(1);
    const total = ref(0);
    const loadingMore = ref(false);
    const loadMoreSentinel = ref(null);
    let observer = null;

    function itemKey(item) {
        return String(item?.uuid ?? item?.slug ?? item?.id ?? '');
    }

    function syncFromPaginator(paginator, append = false) {
        const rows = paginator?.data ?? [];
        total.value = Number(paginator?.total ?? rows.length);
        currentPage.value = Number(paginator?.current_page ?? 1);
        lastPage.value = Number(paginator?.last_page ?? 1);

        if (!append) {
            displayedItems.value = [...rows];
            return;
        }

        const seen = new Set(displayedItems.value.map(itemKey));
        displayedItems.value = [
            ...displayedItems.value,
            ...rows.filter((item) => !seen.has(itemKey(item))),
        ];
    }

    watch(getPaginator, (paginator) => {
        if (Number(paginator?.current_page ?? 1) <= 1) {
            syncFromPaginator(paginator, false);
        }
    }, { immediate: true, deep: true });

    const hasMore = computed(() => currentPage.value < lastPage.value);

    function loadMore() {
        if (loadingMore.value || !hasMore.value) {
            return;
        }

        loadingMore.value = true;
        const nextPage = currentPage.value + 1;
        const onlyProps = only ? [only] : undefined;

        router.get(getUrl(), {
            ...buildQuery(),
            [pageParam]: nextPage,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: onlyProps,
            onSuccess: (visit) => {
                const paginator = only ? visit.props[only] : getPaginator();
                syncFromPaginator(paginator, true);
                loadingMore.value = false;
            },
            onError: () => {
                loadingMore.value = false;
            },
            onFinish: () => {
                loadingMore.value = false;
            },
        });
    }

    function disconnectObserver() {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
    }

    function setupObserver() {
        disconnectObserver();

        if (!loadMoreSentinel.value || !hasMore.value) {
            return;
        }

        observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    loadMore();
                }
            },
            { root: null, rootMargin, threshold: 0 },
        );

        observer.observe(loadMoreSentinel.value);
    }

    watch(loadMoreSentinel, () => setupObserver());
    watch(hasMore, () => setupObserver());

    onBeforeUnmount(() => disconnectObserver());

    return {
        displayedItems,
        currentPage,
        lastPage,
        total,
        loadingMore,
        loadMoreSentinel,
        hasMore,
        loadMore,
        setupObserver,
    };
}
