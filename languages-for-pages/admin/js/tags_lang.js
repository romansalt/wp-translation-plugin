jQuery(document).ready(function($) {
    const $inlineEditor = inlineEditTax;
    const origFunction = $inlineEditor.edit;
    
    $inlineEditor.edit = function(id) {
        origFunction.apply(this, arguments);
        
        const termId = (typeof id === 'object') ? this.getId(id) : id;
        
        const row = $('#tag-' + termId);
        const editRow = $('#edit-' + termId);

        let language = row.find('.custom-language-display').data('lang-code');
        
        if (!language) {
            language = row.data('language') || '';
        }
        
        const $languageField = editRow.find('select[name="custom_language"]');
        if ($languageField.length) {
            $languageField.val(language);
        }
    }
});