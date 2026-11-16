/**
 * Набор расширений TipTap для редакторов админки.
 *
 * TipTap — не «редактор HTML», а редактор документа со строгой схемой:
 * всё, чему в схеме нет узла, при загрузке молча выбрасывается, а следующее
 * сохранение записывает уже обрезанный документ. Так пропадали таблицы —
 * StarterKit их не содержит, и прайс тура превращался в столбик значений
 * (жалоба заказчика 2026-08-13). Поэтому здесь набор расширений живёт
 * одним списком на оба поля: разъехавшиеся копии мы уже проходили с CSRF.
 *
 * Таблицы в контенте — перенесённые из Битрикса, свёрстанные Вордом:
 * `<table border="1" cellspacing="0" cellpadding="0">`, выравнивание ячеек
 * через `style`, colspan до 13. Ни один из этих атрибутов схема TipTap не
 * знает, а рисует их публичная часть — поэтому атрибуты пробрасываем как
 * есть: круговой прогон через редактор не должен менять вид таблицы.
 */

// Оформление содержимого редактора — рядом с расширениями, чтобы поля не
// разъезжались: без этих правил Tailwind сбрасывает заголовки и списки.
import './editor.css';

import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import { TextStyleKit } from '@tiptap/extension-text-style';
import { TextAlign } from '@tiptap/extension-text-align';

/** Атрибут, который сохраняем как есть — TipTap про него ничего не знает. */
function passthroughAttribute(name) {
  return {
    default: null,
    parseHTML: (element) => element.getAttribute(name),
    renderHTML: (attributes) => (attributes[name] ? { [name]: attributes[name] } : {}),
  };
}

/** Расширение, дополненное сквозными атрибутами поверх собственных. */
function keeping(extension, names) {
  return extension.extend({
    addAttributes() {
      const attributes = { ...(this.parent?.() ?? {}) };

      names.forEach((name) => {
        attributes[name] = passthroughAttribute(name);
      });

      return attributes;
    },
  });
}

// Вёрстка ячеек в перенесённом контенте держится на style и colspan/rowspan
// (последние TipTap знает сам), остальное — наследие Ворда и старых шаблонов.
const CELL_ATTRIBUTES = ['style', 'class', 'align', 'valign', 'bgcolor', 'width'];
const TABLE_ATTRIBUTES = ['style', 'class', 'border', 'cellpadding', 'cellspacing', 'width', 'align'];
const ROW_ATTRIBUTES = ['style', 'class'];

/**
 * Выравнивание абзаца — четыре варианта, как в редакторе Битрикса.
 * Порядок этого списка — порядок кнопок на панели.
 */
const ALIGNMENTS = [
  { value: 'left', name: 'alignLeft', title: 'По левому краю' },
  { value: 'center', name: 'alignCenter', title: 'По центру' },
  { value: 'right', name: 'alignRight', title: 'По правому краю' },
  { value: 'justify', name: 'alignJustify', title: 'По ширине страницы' },
];

// Кому выравнивание разрешено. Ячеек таблиц в списке нет: их style и align
// уже переносятся сквозными атрибутами, а два атрибута, пишущих один и тот же
// style, затёрли бы друг друга. Абзацу внутри ячейки это не мешает.
const ALIGNED_NODES = ['paragraph', 'heading', 'blockquote'];

/**
 * Выравнивание абзацев.
 *
 * Перенесённый контент размечен двумя способами сразу: `style="text-align:
 * justify"` и вордовским `align="center"` (второй — в прайсах, внутри ячеек).
 * Родное расширение читает только style, а всё, чего схема не знает, теряется
 * при первом же сохранении — поэтому читаем оба, а пишем style, как браузер.
 */
const Alignment = TextAlign.extend({
  addGlobalAttributes() {
    const { types, alignments } = this.options;

    return [
      {
        types,
        attributes: {
          textAlign: {
            default: null,
            // style важнее align: в контенте встречается
            // `<p align="center" style="text-align: left;">`, и такой абзац
            // браузер рисует слева.
            parseHTML: (element) => {
              const value = element.style.textAlign || element.getAttribute('align') || '';

              return alignments.includes(value) ? value : null;
            },
            renderHTML: (attributes) =>
              attributes.textAlign ? { style: `text-align: ${attributes.textAlign}` } : {},
          },
        },
      },
    ];
  },
}).configure({ types: ALIGNED_NODES, alignments: ALIGNMENTS.map((alignment) => alignment.value) });

/**
 * @param {{placeholder?: string, extra?: Array}} options
 */
export function editorExtensions({ placeholder = 'Начните вводить текст...', extra = [] } = {}) {
  return [
    // link и underline входят в StarterKit; выключаем, чтобы не дублировать
    // их своими — TipTap на одинаковые имена ругается в консоль.
    StarterKit.configure({ link: false, underline: false }),
    Underline,
    Link.configure({ openOnClick: false }),
    // Картинка — часть строки, а не отдельный блок. В перенесённом контенте
    // она и так лежит внутри абзаца (`<p align="center"><img>`), и блочная
    // картинка выдёргивалась оттуда наружу: абзац оставался пустым, а
    // фотография — прижатой влево, и выровнять её было нечем.
    Image.configure({ inline: true }),
    // Цвет, фон и размер текста: в тех же таблицах красным помечено
    // «мест нет», и без TextStyle пометка стала бы обычным текстом.
    TextStyleKit,
    Alignment,
    keeping(Table, TABLE_ATTRIBUTES),
    keeping(TableRow, ROW_ATTRIBUTES),
    keeping(TableHeader, CELL_ATTRIBUTES),
    keeping(TableCell, CELL_ATTRIBUTES),
    Placeholder.configure({ placeholder }),
    ...extra,
  ];
}

/** Кнопки работы с таблицей. Часть видна, только когда курсор внутри таблицы. */
export function tableToolbarButtons(editor, icons) {
  const inTable = () => Boolean(editor.value?.isActive('table'));
  const chain = () => editor.value?.chain().focus();

  return [
    {
      name: 'table',
      title: 'Вставить таблицу',
      icon: icons.table,
      action: () => chain()?.insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
    },
    {
      name: 'addRowAfter',
      title: 'Добавить строку',
      icon: icons.rowPlus,
      action: () => chain()?.addRowAfter().run(),
      visible: inTable,
    },
    {
      name: 'deleteRow',
      title: 'Удалить строку',
      icon: icons.rowMinus,
      action: () => chain()?.deleteRow().run(),
      visible: inTable,
    },
    {
      name: 'addColumnAfter',
      title: 'Добавить столбец',
      icon: icons.columnPlus,
      action: () => chain()?.addColumnAfter().run(),
      visible: inTable,
    },
    {
      name: 'deleteColumn',
      title: 'Удалить столбец',
      icon: icons.columnMinus,
      action: () => chain()?.deleteColumn().run(),
      visible: inTable,
    },
    {
      name: 'mergeOrSplit',
      title: 'Объединить или разделить ячейки',
      icon: icons.mergeCells,
      action: () => chain()?.mergeOrSplit().run(),
      visible: inTable,
    },
    {
      name: 'deleteTable',
      title: 'Удалить таблицу',
      icon: icons.tableMinus,
      action: () => chain()?.deleteTable().run(),
      visible: inTable,
    },
  ];
}

/** Кнопки выравнивания абзаца. */
export function alignToolbarButtons(editor, icons) {
  // Абзац без атрибута выровнен по левому краю: подсвечиваем «слева», иначе
  // на обычном тексте ни одна из четырёх кнопок не выглядит нажатой.
  const current = () =>
    ALIGNMENTS.find(({ value }) => editor.value?.isActive({ textAlign: value }))?.value ?? 'left';

  return ALIGNMENTS.map(({ value, name, title }) => ({
    name,
    title,
    icon: icons[name],
    action: () => editor.value?.chain().focus().setTextAlign(value).run(),
    isActive: () => current() === value,
  }));
}

/**
 * Позиция каретки, которую видит пользователь.
 *
 * TipTap узнаёт о клике мышью не сразу: ProseMirror читает выделение из DOM
 * отложенно, через таймер. Клик по кнопке панели уводит фокус из редактора
 * раньше, чем это чтение случится, и тогда редактор так и остаётся думать,
 * что курсор в самом начале поля — картинка из медиабиблиотеки встаёт в
 * начало текста, а страница ещё и прокручивается туда (жалоба заказчика
 * 27.08.2026: «картинка становится здесь, хотя курсор стоит в первом месте»).
 * Промах редкий — успеет чтение или нет, зависит от того, насколько занят
 * браузер: карточка тура на 11 дней с фотографиями тормозит его заметно.
 *
 * Поэтому позицию читаем сами и заранее — в момент нажатия на кнопку, пока
 * фокус ещё в редакторе. В DOM каретка к этому времени уже стоит на месте.
 *
 * Возвращает null, если каретки в этом редакторе нет: в поле не заходили, и
 * «текущей позиции» в нём просто не существует.
 */
export function caretPosition(editor) {
  const view = editor?.view;
  const selection = typeof window === 'undefined' ? null : window.getSelection();

  if (!view || !selection || selection.rangeCount === 0) {
    return null;
  }

  const { anchorNode, anchorOffset, focusNode, focusOffset } = selection;

  if (!anchorNode || !view.dom.contains(anchorNode)) {
    return null;
  }

  try {
    const anchor = view.posAtDOM(anchorNode, anchorOffset);
    const head =
      focusNode && view.dom.contains(focusNode) ? view.posAtDOM(focusNode, focusOffset) : anchor;

    return { from: Math.min(anchor, head), to: Math.max(anchor, head) };
  } catch {
    // posAtDOM ругается на узлы, которых нет в документе (например, каретка
    // осталась в куске, который редактор уже перерисовал).
    return null;
  }
}

/**
 * Вставка файла из медиабиблиотеки.
 *
 * `at` — позиция, запомненная при нажатии кнопки. Без неё кладём файл в конец
 * текста: курсор в поле не ставили, и начало текста — самое неудачное место,
 * которое можно выбрать за пользователя (именно так выглядела ошибка).
 * TipTap сам прижмёт позицию к границам документа, если текст успел измениться.
 */
export function insertLibraryFile(editor, file, at = null) {
  if (!editor) {
    return;
  }

  const end = editor.state.doc.content.size;
  const chain = editor.chain().focus().setTextSelection(at ?? { from: end, to: end });

  if (file.is_image) {
    chain.setImage({ src: file.url });
  } else {
    const linkText = file.filename || file.url;

    chain.insertContent(`<a href="${file.url}" target="_blank">${linkText}</a>`);
  }

  chain.run();
}
