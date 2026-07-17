import printJS from 'print-js'

/**
 * 通用单据打印 composable
 *
 * @param {Object} data  从后端 ReportController 获取的打印数据，格式见 API 文档
 *
 * 使用方式：
 *   import { usePrint } from '@/composables/usePrint'
 *   const res = await getStockInPrintApi(id)
 *   usePrint(res.data)
 */
export function usePrint(data) {
  // 构建打印 HTML（纯内联样式，兼容打印）
  const html = `
    <div style="max-width: 800px; margin: 0 auto; font-family: SimSun, 'Microsoft YaHei', serif; color: #000;">
      <h2 style="text-align:center; margin-bottom:5px; font-size:22px;">${data.title || '单据'}</h2>
      <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:16px; border-bottom:1px solid #333; padding-bottom:8px;">
        <span>单号：${data.order_no || ''}</span>
        ${data.supplier ? `<span>供应商：${data.supplier}</span>` : ''}
        ${data.recipient ? `<span>领用人：${data.recipient}</span>` : ''}
        <span>经办人：${data.operator || ''}</span>
        <span>日期：${data.created_at || ''}</span>
        ${data.status_text ? `<span>状态：${data.status_text}</span>` : ''}
      </div>
      <table style="width:100%; border-collapse:collapse; font-size:14px;">
        <thead>
          <tr style="background:#f5f5f5;">
            <th style="border:1px solid #333; padding:6px; text-align:center;">序号</th>
            <th style="border:1px solid #333; padding:6px;">商品编码</th>
            <th style="border:1px solid #333; padding:6px;">商品名称</th>
            <th style="border:1px solid #333; padding:6px;">规格</th>
            <th style="border:1px solid #333; padding:6px; text-align:center;">单位</th>
            ${data.items && data.items[0] && data.items[0].system_qty !== undefined ? '<th style="border:1px solid #333; padding:6px; text-align:center;">系统库存</th>' : ''}
            <th style="border:1px solid #333; padding:6px; text-align:center;">${data.items && data.items[0] && data.items[0].system_qty !== undefined ? '实盘数' : '数量'}</th>
            ${data.items && data.items[0] && data.items[0].diff_qty !== undefined ? '<th style="border:1px solid #333; padding:6px; text-align:center;">差异</th>' : ''}
            ${data.items && data.items[0] && data.items[0].unit_price !== undefined ? '<th style="border:1px solid #333; padding:6px; text-align:right;">单价</th><th style="border:1px solid #333; padding:6px; text-align:right;">金额</th>' : ''}
          </tr>
        </thead>
        <tbody>
          ${(data.items || []).map((item, i) => `
            <tr>
              <td style="border:1px solid #333; padding:6px; text-align:center;">${i + 1}</td>
              <td style="border:1px solid #333; padding:6px;">${item.code || ''}</td>
              <td style="border:1px solid #333; padding:6px;">${item.name || ''}</td>
              <td style="border:1px solid #333; padding:6px;">${item.spec || ''}</td>
              <td style="border:1px solid #333; padding:6px; text-align:center;">${item.unit || ''}</td>
              ${item.system_qty !== undefined ? `<td style="border:1px solid #333; padding:6px; text-align:center;">${item.system_qty}</td>` : ''}
              <td style="border:1px solid #333; padding:6px; text-align:center;">${item.quantity ?? item.actual_qty ?? ''}</td>
              ${item.diff_qty !== undefined ? `<td style="border:1px solid #333; padding:6px; text-align:center; ${item.diff_qty > 0 ? 'color: green;' : item.diff_qty < 0 ? 'color: red;' : ''}">${item.diff_qty > 0 ? '+' : ''}${item.diff_qty}</td>` : ''}
              ${item.unit_price !== undefined ? `<td style="border:1px solid #333; padding:6px; text-align:right;">${item.unit_price}</td>` : ''}
              ${item.subtotal !== undefined ? `<td style="border:1px solid #333; padding:6px; text-align:right;">${item.subtotal}</td>` : ''}
            </tr>
          `).join('')}
        </tbody>
        ${data.total ? `
        <tfoot>
          <tr>
            <td colspan="${7 + (data.items && data.items[0] && data.items[0].system_qty !== undefined ? 2 : 0)}" style="border:1px solid #333; padding:6px; text-align:right; font-weight:bold;">合计：</td>
            <td style="border:1px solid #333; padding:6px; text-align:right; font-weight:bold;">${data.total}</td>
          </tr>
        </tfoot>` : ''}
      </table>
      ${data.confirmed_at ? `<p style="text-align:right; font-size:14px; margin-top:20px;">确认时间：${data.confirmed_at}</p>` : ''}
    </div>
  `

  printJS({
    printable: html,
    type: 'raw-html',
    style: `
      @media print {
        @page { size: A4; margin: 15mm; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      }
    `,
  })
}
