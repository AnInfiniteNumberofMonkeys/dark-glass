/**
 * Block List Keyboard Shortcut
 * Converts selected blocks to bullet list with Shift+Cmd+B (Mac) or Shift+Ctrl+B (Windows)
 * Converts selected blocks to numbered list with Shift+Cmd+1 (Mac) or Shift+Ctrl+1 (Windows)
 *
 * Absorbed into Infinite Monkeys Dark Glass from the standalone
 * "Block List Keyboard Shortcut" plugin (v1.1.0). Logic unchanged.
 *
 * @package InfiniteMonkeysDarkGlass
 */

(function() {
    'use strict';

    const { registerShortcut } = wp.keyboardShortcuts;
    const { createBlock } = wp.blocks;

    // Register the keyboard shortcuts when DOM is ready
    wp.domReady(() => {
        // Register bullet list shortcut
        if (registerShortcut) {
            registerShortcut({
                name: 'imdg-block-list-shortcut/convert-to-bullets',
                category: 'block',
                description: 'Convert selected blocks to bullet list',
                keyCombination: {
                    modifier: 'primaryShift', // Cmd+Shift on Mac, Ctrl+Shift on Windows
                    character: 'b',
                },
            });

            // Register numbered list shortcut
            registerShortcut({
                name: 'imdg-block-list-shortcut/convert-to-numbered',
                category: 'block',
                description: 'Convert selected blocks to numbered list',
                keyCombination: {
                    modifier: 'primaryShift', // Cmd+Shift on Mac, Ctrl+Shift on Windows
                    character: '1',
                },
            });
        }

        // Listen for the keyboard shortcuts
        document.addEventListener('keydown', handleKeyboardShortcut);
    });

    /**
     * Handle keyboard shortcut event
     *
     * @param {KeyboardEvent} event - The keyboard event
     */
    function handleKeyboardShortcut(event) {
        // Check for Shift + Cmd/Ctrl
        const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
        const modifierKey = isMac ? event.metaKey : event.ctrlKey;

        if (!event.shiftKey || !modifierKey) {
            return;
        }

        // Check for B key (bullet list)
        if (event.key.toLowerCase() === 'b') {
            event.preventDefault();
            event.stopPropagation();
            convertSelectedBlocksToList(false); // false = unordered/bullet list
        }

        // Check for 1 key (numbered list)
        if (event.key === '1' || event.key === '!') {
            event.preventDefault();
            event.stopPropagation();
            convertSelectedBlocksToList(true); // true = ordered/numbered list
        }
    }

    /**
     * Convert selected blocks to a list (bullet or numbered)
     *
     * @param {boolean} ordered - Whether to create an ordered (numbered) list
     */
    function convertSelectedBlocksToList(ordered) {
        // Check if we're in the block editor
        if (!wp.data.select('core/block-editor')) {
            console.warn('Block List Shortcut: Block editor not available');
            return;
        }

        const { getSelectedBlockClientIds, getBlocksByClientId } = wp.data.select('core/block-editor');
        const { replaceBlocks } = wp.data.dispatch('core/block-editor');

        // Get selected block IDs
        const selectedBlockIds = getSelectedBlockClientIds();

        if (selectedBlockIds.length === 0) {
            console.log('Block List Shortcut: No blocks selected');
            return;
        }

        // Get the actual block objects
        const selectedBlocks = getBlocksByClientId(selectedBlockIds);

        if (!selectedBlocks || selectedBlocks.length === 0) {
            console.warn('Block List Shortcut: Could not retrieve selected blocks');
            return;
        }

        // Extract text content from each block
        const listItems = selectedBlocks.map(block => {
            return extractContentFromBlock(block);
        }).flat(); // Flatten in case we had nested lists

        // Filter out empty items
        const validListItems = listItems.filter(content => content && content.trim() !== '');

        if (validListItems.length === 0) {
            console.log('Block List Shortcut: No valid content to convert');
            return;
        }

        // Create list item blocks
        const listItemBlocks = validListItems.map(content =>
            createBlock('core/list-item', {
                content: content
            })
        );

        // Create the list block with the items
        const listBlock = createBlock('core/list', {
            ordered: ordered, // false = bullet list, true = numbered list
            className: '' // Add custom class if needed
        }, listItemBlocks);

        // Replace the selected blocks with the new list block
        replaceBlocks(selectedBlockIds, listBlock);

        const listType = ordered ? 'numbered' : 'bullet';
        console.log('Block List Shortcut: Converted ' + selectedBlockIds.length + ' block(s) to ' + listType + ' list');
    }

    /**
     * Extract content from a block based on its type
     *
     * @param {Object} block - The block object
     * @return {string|Array} - Extracted content
     */
    function extractContentFromBlock(block) {
        if (!block) {
            return '';
        }

        let content = '';

        // Handle different block types
        switch (block.name) {
            case 'core/paragraph':
            case 'core/heading':
                content = block.attributes.content || '';
                break;

            case 'core/list':
                // If already a list, get its items
                if (block.innerBlocks && block.innerBlocks.length > 0) {
                    return block.innerBlocks.map(item =>
                        item.attributes.content || ''
                    );
                }
                break;

            case 'core/quote':
                content = block.attributes.value || '';
                break;

            case 'core/verse':
                content = block.attributes.content || '';
                break;

            case 'core/preformatted':
                content = block.attributes.content || '';
                break;

            default:
                // For other blocks, try to get any text content
                content = block.attributes.content ||
                         block.attributes.text ||
                         block.attributes.value || '';
        }

        return content;
    }

})();
