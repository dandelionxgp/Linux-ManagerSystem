<template>
  <div>
    <!-- 统计卡片 -->
    <el-row :gutter="20">
      <el-col :span="6">
        <div class="stat-card" style="border-left-color: #409EFF">
          <div class="stat-icon" style="background: #ecf5ff">
            <el-icon :size="28" color="#409EFF"><Goods /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-label">商品总数</div>
            <div class="stat-value">{{ stats.product_count ?? '-' }}</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card" style="border-left-color: #67C23A">
          <div class="stat-icon" style="background: #f0f9eb">
            <el-icon :size="28" color="#67C23A"><Upload /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-label">本月入库单</div>
            <div class="stat-value">{{ stats.monthly_stock_in ?? '-' }}</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card" style="border-left-color: #E6A23C">
          <div class="stat-icon" style="background: #fdf6ec">
            <el-icon :size="28" color="#E6A23C"><Download /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-label">本月出库单</div>
            <div class="stat-value">{{ stats.monthly_stock_out ?? '-' }}</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card" style="border-left-color: #F56C6C">
          <div class="stat-icon" style="background: #fef0f0">
            <el-icon :size="28" color="#F56C6C"><WarningFilled /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-label">库存预警</div>
            <div class="stat-value" style="color: #F56C6C">{{ stats.alert_count ?? '-' }}</div>
          </div>
        </div>
      </el-col>
    </el-row>

    <!-- 库存价值 -->
    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>
            <span style="font-weight: 600">库存总价值</span>
          </template>
          <div style="font-size: 28px; font-weight: bold; color: #303133">
            ¥{{ formatMoney(stats.total_stock_value) }}
          </div>
          <div style="color: #909399; font-size: 13px; margin-top: 4px">
            按参考进价 × 当前库存计算
          </div>
        </el-card>
      </el-col>
      <el-col :span="16">
        <el-card shadow="hover">
          <template #header>
            <span style="font-weight: 600">近7天出入库趋势</span>
          </template>
          <!-- 纯 CSS 柱状图 -->
          <div class="trend-chart" v-if="trend.length">
            <div class="trend-bar-group" v-for="(item, i) in trend" :key="i">
              <div class="bars">
                <div class="bar bar-in" :style="{ height: barHeight(item.in) }"
                  :title="`入库: ${item.in}`">
                  <span class="bar-label" v-if="item.in > 0">{{ item.in }}</span>
                </div>
                <div class="bar bar-out" :style="{ height: barHeight(item.out) }"
                  :title="`出库: ${item.out}`">
                  <span class="bar-label" v-if="item.out > 0">{{ item.out }}</span>
                </div>
              </div>
              <div class="bar-date">{{ item.date }}</div>
            </div>
          </div>
          <div v-else style="text-align: center; color: #c0c4cc; padding: 30px 0">
            暂无趋势数据
          </div>
          <div class="trend-legend">
            <span><i class="legend-dot" style="background:#67C23A"></i>入库</span>
            <span><i class="legend-dot" style="background:#F56C6C"></i>出库</span>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 最近操作记录 -->
    <el-card shadow="hover" style="margin-top: 20px">
      <template #header>
        <span style="font-weight: 600">最近操作记录</span>
      </template>
      <el-table :data="recentLogs" stripe border size="small" v-loading="loading">
        <el-table-column prop="username" label="操作人" width="120">
          <template #default="{ row }">
            {{ row.real_name || row.username }}
          </template>
        </el-table-column>
        <el-table-column label="类型" width="90">
          <template #default="{ row }">
            <el-tag v-if="row.action === 'create'" type="success" size="small">创建</el-tag>
            <el-tag v-else-if="row.action === 'update'" type="warning" size="small">更新</el-tag>
            <el-tag v-else-if="row.action === 'delete'" type="danger" size="small">删除</el-tag>
            <el-tag v-else-if="row.action === 'reverse'" type="info" size="small">冲销</el-tag>
            <el-tag v-else-if="row.action === 'confirm'" type="primary" size="small">确认</el-tag>
            <el-tag v-else size="small">{{ row.action }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="模块" width="100">
          <template #default="{ row }">
            {{ moduleMap[row.module] || row.module }}
          </template>
        </el-table-column>
        <el-table-column prop="description" label="操作描述" min-width="220" show-overflow-tooltip />
        <el-table-column prop="created_at" label="时间" width="170" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Goods, Upload, Download, WarningFilled } from '@element-plus/icons-vue'
import { getDashboardApi } from '@/api/dashboard'

const loading = ref(false)
const stats = ref({})
const trend = ref([])
const recentLogs = ref([])

const moduleMap = {
  product: '商品', stock_in: '入库', stock_out: '出库',
  inventory: '盘点', user: '用户', category: '分类',
}

onMounted(() => fetchData())

async function fetchData() {
  loading.value = true
  try {
    const res = await getDashboardApi()
    stats.value = res.data.stats || {}
    trend.value = res.data.trend || []
    recentLogs.value = res.data.recent_logs || []
  } finally {
    loading.value = false
  }
}

/** 柱状图高度：最大 120px */
function barHeight(val) {
  if (!trend.value.length) return '0'
  const max = Math.max(...trend.value.map(t => Math.max(t.in, t.out)), 1)
  return Math.round((val / max) * 120) + 'px'
}

/** 金额格式化 */
function formatMoney(val) {
  if (val === undefined || val === null) return '0.00'
  return Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<style scoped>
/* 统计卡片 */
.stat-card {
  display: flex;
  align-items: center;
  background: #fff;
  border-radius: 6px;
  padding: 20px 16px;
  border-left: 4px solid #ccc;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  cursor: default;
  transition: transform 0.15s;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-icon {
  width: 52px; height: 52px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 14px;
  flex-shrink: 0;
}
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 26px; font-weight: bold; color: #303133; }

/* 趋势图 */
.trend-chart {
  display: flex;
  align-items: flex-end;
  justify-content: space-around;
  height: 180px;
  padding: 0 10px;
}
.trend-bar-group {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
}
.bars {
  display: flex;
  align-items: flex-end;
  gap: 4px;
  height: 140px;
}
.bar {
  width: 24px;
  border-radius: 4px 4px 0 0;
  position: relative;
  transition: height 0.3s;
  min-height: 2px;
}
.bar-in  { background: linear-gradient(to top, #67C23A, #95d475); }
.bar-out { background: linear-gradient(to top, #F56C6C, #fab6b6); }
.bar-label {
  position: absolute;
  top: -18px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 10px;
  color: #606266;
  white-space: nowrap;
}
.bar-date { font-size: 11px; color: #909399; margin-top: 6px; }
.trend-legend {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin-top: 8px;
  font-size: 12px;
  color: #606266;
}
.legend-dot {
  display: inline-block;
  width: 10px; height: 10px;
  border-radius: 2px;
  margin-right: 4px;
  vertical-align: middle;
}
</style>
