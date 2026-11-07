@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.jQuery) {
                return;
            }

            var $ = window.jQuery;

            $('[data-quotation-composer]').each(function () {
                var root = $(this);
                var template = root.find('template').first();
                var list = root.find('[data-quotation-items]').first();
                var preview = root.find('[data-quotation-preview]').first();

                if (!template.length || !list.length || !preview.length) {
                    return;
                }

                function cents(value) {
                    var match = String(value == null ? '' : value).trim().match(/^(\d{1,10})(?:\.(\d{1,2}))?$/);

                    if (!match) {
                        return null;
                    }

                    return (parseInt(match[1], 10) * 100) + parseInt((match[2] || '').padEnd(2, '0'), 10);
                }

                function format(amount) {
                    var whole = Math.floor(amount / 100);
                    var fraction = String(amount % 100).padStart(2, '0');

                    return '₱' + whole.toLocaleString('en-US') + '.' + fraction;
                }

                function previewTotal() {
                    var total = 0;
                    var valid = true;

                    list.children('[data-quotation-item]').each(function () {
                        var quantity = cents($(this).find('[name$="[quantity]"]').val());
                        var price = cents($(this).find('[name$="[unit_price]"]').val());
                        var line = quantity !== null && price !== null && quantity > 0 && price >= 0
                            ? Math.floor((quantity * price) / 100)
                            : null;

                        $(this).find('[data-line-preview]').text(line === null ? '' : format(line));

                        if (line === null) {
                            valid = false;
                            return;
                        }

                        total += line;
                    });

                    preview.text(valid
                        ? 'Preview ' + format(total) + '. Saving calculates the total on the server.'
                        : 'Preview only. Saving calculates the total on the server.');
                }

                function reindex() {
                    list.children('[data-quotation-item]').each(function (index) {
                        $(this).find('[name]').each(function () {
                            this.name = this.name.replace(/items\[\d+\]/, 'items[' + index + ']');
                        });
                        $(this).find('[id]').each(function () {
                            this.id = this.id.replace(/items-\d+-/, 'items-' + index + '-');
                        });
                        $(this).find('label[for]').each(function () {
                            this.htmlFor = this.htmlFor.replace(/items-\d+-/, 'items-' + index + '-');
                        });
                    });

                    list.find('[data-remove-item]').prop('disabled', list.children().length < 2);
                    previewTotal();
                }

                root.find('[data-add-quotation-item]').on('click', function () {
                    list.append(template.html().replaceAll('__INDEX__', String(list.children().length)));
                    reindex();
                });

                list.on('click', '[data-remove-item]', function () {
                    if (list.children().length < 2) {
                        return;
                    }

                    $(this).closest('[data-quotation-item]').remove();
                    reindex();
                });

                list.on('input change', 'input, select', previewTotal);
                reindex();
            });
        });
    </script>
@endpush
