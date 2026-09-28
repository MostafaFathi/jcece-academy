import { onBeforeUnmount, watchEffect } from 'vue';

export function usePageMeta(title, description = null) {
    const defaultTitle = document.title;
    let descriptionElement = document.querySelector('meta[name="description"]');
    const defaultDescription = descriptionElement?.getAttribute('content') ?? '';

    watchEffect(() => {
        const resolvedTitle = typeof title === 'function' ? title() : title;
        const resolvedDescription = typeof description === 'function' ? description() : description;

        document.title = resolvedTitle ? `${resolvedTitle} | JCEC Academy` : defaultTitle;

        if (resolvedDescription) {
            if (!descriptionElement) {
                descriptionElement = document.createElement('meta');
                descriptionElement.setAttribute('name', 'description');
                document.head.appendChild(descriptionElement);
            }

            descriptionElement.setAttribute('content', resolvedDescription);
        }
    });

    onBeforeUnmount(() => {
        document.title = defaultTitle;
        descriptionElement?.setAttribute('content', defaultDescription);
    });
}
