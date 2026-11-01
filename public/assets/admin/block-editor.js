import {
    Alignment,
    AutoLink,
    Autoformat,
    BlockQuote,
    Bold,
    ClassicEditor,
    Essentials,
    Heading,
    Image,
    ImageCaption,
    ImageTextAlternative,
    ImageToolbar,
    ImageUpload,
    Italic,
    Link,
    List,
    Paragraph,
    PasteFromOffice,
    SourceEditing,
    Table,
    TableToolbar,
    Undo
} from 'ckeditor5';

(function () {
    var field = document.querySelector('[data-block-editor]');
    if (!field) {
        return;
    }

    var form = field.closest('form');
    var uploadUrl = field.getAttribute('data-upload-url') || '';
    var csrfName = field.getAttribute('data-csrf-name') || 'csrf_test_name';

    function tokenField() {
        return form ? form.querySelector('input[name="' + csrfName + '"]') : null;
    }

    function token() {
        var input = tokenField();
        return input ? input.value : '';
    }

    function setToken(value) {
        var input = tokenField();
        if (input && value) {
            input.value = value;
        }
    }

    function BlockUploadAdapter(loader) {
        this.loader = loader;
    }

    BlockUploadAdapter.prototype.upload = function () {
        var loader = this.loader;

        return loader.file.then(function (file) {
            var data = new FormData();
            data.append('image', file);
            data.append(csrfName, token());

            return fetch(uploadUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                return response.text().then(function (text) {
                    var payload = null;
                    try {
                        payload = JSON.parse(text);
                    } catch (error) {
                        payload = null;
                    }

                    if (payload && payload.csrf) {
                        setToken(payload.csrf);
                    }

                    var name = payload && payload.data && payload.data.files ? payload.data.files[0] : '';
                    if (!response.ok || !payload || payload.success !== true || !name) {
                        var message = payload && payload.data && payload.data.messages
                            ? payload.data.messages.join(' ')
                            : 'The image could not be uploaded.';
                        return Promise.reject(message);
                    }

                    return { default: payload.data.baseurl + name };
                });
            }).catch(function (error) {
                if (typeof error === 'string' && error !== '') {
                    return Promise.reject(error);
                }

                return Promise.reject('The image could not be uploaded.');
            });
        });
    };

    BlockUploadAdapter.prototype.abort = function () {};

    ClassicEditor.create(field, {
        licenseKey: 'GPL',
        plugins: [
            Essentials,
            Undo,
            Paragraph,
            Heading,
            Bold,
            Italic,
            Alignment,
            List,
            Link,
            AutoLink,
            Image,
            ImageCaption,
            ImageToolbar,
            ImageTextAlternative,
            ImageUpload,
            Table,
            TableToolbar,
            BlockQuote,
            PasteFromOffice,
            Autoformat,
            SourceEditing
        ],
        toolbar: {
            items: [
                'undo', 'redo', '|',
                'heading', '|',
                'bold', 'italic', '|',
                'alignment', '|',
                'bulletedList', 'numberedList', '|',
                'link', 'uploadImage', 'insertTable', 'blockQuote', '|',
                'sourceEditing'
            ],
            shouldNotGroupWhenFull: true
        },
        heading: {
            options: [
                { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' }
            ]
        },
        alignment: {
            options: ['left', 'center', 'right', 'justify']
        },
        image: {
            toolbar: ['toggleImageCaption', 'imageTextAlternative']
        },
        table: {
            contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
        },
        link: {
            addTargetToExternalLinks: true,
            defaultProtocol: 'https://'
        }
    }).then(function (editor) {
        editor.plugins.get('FileRepository').createUploadAdapter = function (loader) {
            return new BlockUploadAdapter(loader);
        };

        if (form) {
            form.addEventListener('submit', function () {
                editor.updateSourceElement();
            }, true);
        }
    }).catch(function () {
        field.style.display = '';
    });
}());
