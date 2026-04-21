import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import ItemActionsMenu from '@/Components/Files/ItemActionsMenu.vue';

function mountMenu(type: 'file' | 'folder' = 'file', downloadUrl = '/download/1') {
    return mount(ItemActionsMenu, {
        props: { item: { id: 1, type }, downloadUrl },
    });
}

function menuLabels(wrapper: ReturnType<typeof mountMenu>) {
    return wrapper.findAll('.p-menu li span').map((s) => s.text());
}

describe('ItemActionsMenu', () => {
    it('renders the trigger button with an accessible label', () => {
        const wrapper = mountMenu();
        const btn = wrapper.get('button');

        expect(btn.attributes('aria-label')).toBe('common.actions');
    });

    it('lists open, rename, share, download, delete for a file', () => {
        const wrapper = mountMenu('file');

        const labels = menuLabels(wrapper);
        expect(labels).toEqual(
            expect.arrayContaining(['files.open', 'files.rename', 'files.share', 'files.download', 'files.delete']),
        );
    });

    it('omits download for a folder', () => {
        const wrapper = mountMenu('folder');

        expect(menuLabels(wrapper)).not.toContain('files.download');
    });

    it('emits open when the open item is clicked', async () => {
        const wrapper = mountMenu();

        const openLink = wrapper.findAll('.p-menu li a').find((a) => a.text().includes('files.open'));
        await openLink!.trigger('click');

        expect(wrapper.emitted('open')).toBeTruthy();
    });

    it('emits delete when the destructive row is clicked', async () => {
        const wrapper = mountMenu();

        const deleteLink = wrapper.findAll('.p-menu li a').find((a) => a.text().includes('files.delete'));
        await deleteLink!.trigger('click');

        expect(wrapper.emitted('delete')).toBeTruthy();
    });

    it('tags the delete row with the danger class for theming', () => {
        const wrapper = mountMenu();

        expect(wrapper.find('.file-action-danger').exists()).toBe(true);
    });

    it('passes the download url through to the download menu item', () => {
        const wrapper = mountMenu('file', '/media/42.zip');

        const hrefs = wrapper.findAll('.p-menu li a').map((a) => a.attributes('href'));
        expect(hrefs).toContain('/media/42.zip');
    });
});
