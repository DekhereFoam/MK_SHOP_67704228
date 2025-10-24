<template>
  <div class="container my-5">
    <h2 class="text-center mb-4">📋 รายการคำสั่งซื้อทั้งหมด</h2>

    <!-- 🔍 ตัวกรองค้นหาและสถานะ -->
    <div class="mb-3">
      <div class="row g-2 align-items-center">
        <div class="col-md-3">
          <select v-model="searchBy" class="form-select">
            <option value="table_no">ค้นหาตามโต๊ะ</option>
            <option value="order_id">ค้นหาตามรหัสคำสั่งซื้อ</option>
          </select>
        </div>
        <div class="col-md-3">
          <input
            type="text"
            class="form-control"
            :placeholder="searchBy === 'table_no' ? '🔍 กรอกหมายเลขโต๊ะ...' : '🔍 กรอกรหัสคำสั่งซื้อ...'"
            v-model="searchKeyword"
          />
        </div>
        <div class="col-md-3">
          <select v-model="statusFilter" class="form-select">
            <option value="">ทุกสถานะ</option>
            <option value="รอดำเนินการ">รอดำเนินการ</option>
            <option value="ยกเลิก">ยกเลิก</option>
            <option value="เสร็จแล้ว">เสร็จแล้ว</option>
          </select>
        </div>
      </div>
    </div>

    <!-- 🏷️ ปุ่มกรองประเภทสินค้า -->
    <div class="mb-3">
      <label class="fw-bold mb-2">ประเภทสินค้า:</label>
      <div class="d-flex flex-wrap gap-2">
        <button 
          class="btn btn-sm"
          :class="categoryFilter === '' ? 'btn-primary' : 'btn-outline-primary'"
          @click="categoryFilter = ''"
        >
          ทั้งหมด
        </button>
        <button 
          v-for="cat in categories" 
          :key="cat.id"
          class="btn btn-sm"
          :class="categoryFilter === cat.id ? 'btn-success' : 'btn-outline-success'"
          @click="categoryFilter = cat.id"
        >
          {{ cat.name }}
        </button>
      </div>
    </div>

    <!-- แสดงต่อหน้า + ปุ่มลบ -->
    <div class="mb-3 d-flex justify-content-between align-items-center">
      <div>
        <label class="me-2">แสดงต่อหน้า:</label>
        <select v-model.number="rowsPerPage" class="form-select d-inline-block w-auto">
          <option :value="5">5</option>
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
        </select>
      </div>
      <button class="btn btn-danger" @click="deleteAllCancelledOrders">
        ลบรายการยกเลิกทั้งหมด
      </button>
    </div>

    <!-- ⏳ กำลังโหลด -->
    <div v-if="loading" class="text-center">⏳ กำลังโหลดข้อมูล...</div>
    <div v-if="error" class="text-danger text-center">{{ error }}</div>

    <!-- 📋 ตารางแสดงข้อมูล -->
    <table v-if="paginatedOrders.length > 0" class="table table-bordered table-striped mt-3">
      <thead class="table-primary text-center">
        <tr>
          <th>รหัสคำสั่งซื้อ</th>
          <th>โต๊ะ</th>
          <th>รหัสสินค้า</th>
          <th>สินค้า</th>
          <th>ประเภท</th>
          <th>จำนวน</th>
          <th>ราคา/หน่วย</th>
          <th>สถานะ</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(order, index) in paginatedOrders" :key="index">
          <td class="text-center">{{ order.order_id }}</td>
          <td class="text-center">{{ order.table_no }}</td>
          <td class="text-center">{{ order.product_id }}</td>
          <td>{{ order.product_name }}</td>
          <td class="text-center">
            <span class="badge bg-info">{{ getCategoryName(order.type_id) }}</span>
          </td>
          <td class="text-center">{{ order.quantity }}</td>
          <td class="text-end">{{ order.price.toFixed(2) }}</td>
          <td class="text-center">
            <select v-model="order.status" @change="updateStatus(order)" class="form-select form-select-sm">
              <option value="รอดำเนินการ">รอดำเนินการ</option>
              <option value="ยกเลิก">ยกเลิก</option>
              <option value="เสร็จแล้ว">เสร็จแล้ว</option>
            </select>
          </td>
          <td class="text-center">
            <button
              v-if="order.status === 'ยกเลิก'"
              class="btn btn-danger btn-sm"
              @click="deleteOrder(order)"
            >
              ลบ
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-else-if="!loading" class="text-center text-muted">
      ❗ ยังไม่มีข้อมูลคำสั่งซื้อ
    </div>

    <!-- 📄 ปุ่มเปลี่ยนหน้า -->
    <div v-if="totalPages > 1" class="d-flex justify-content-center align-items-center mt-4">
      <button class="btn btn-secondary me-2" :disabled="currentPage === 1" @click="currentPage--">
        ⬅ ก่อนหน้า
      </button>
      <span>หน้า {{ currentPage }} จาก {{ totalPages }}</span>
      <button class="btn btn-secondary ms-2" :disabled="currentPage === totalPages" @click="currentPage++">
        ถัดไป ➡
      </button>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed, watch } from "vue";

export default {
  name: "OrderList",
  setup() {
    const orders = ref([]);
    const categories = ref([
      { id: 1, name: 'อาหาร' },
      { id: 2, name: 'เครื่องดื่ม' },
      { id: 3, name: 'ของหวาน' },
      { id: 4, name: 'ของทานเล่น' }
    ]);
    const loading = ref(true);
    const error = ref(null);
    const searchKeyword = ref("");
    const searchBy = ref("table_no");
    const statusFilter = ref("");
    const categoryFilter = ref("");
    const currentPage = ref(1);
    const rowsPerPage = ref(10);

    const fetchOrders = async () => {
      loading.value = true;
      error.value = null;
      try {
        const res = await fetch("http://localhost:8081/MK_SHOP/php_api/show_orders.php");
        const data = await res.json();
        if (data.success && Array.isArray(data.data)) {
          orders.value = data.data.map((o) => ({
            order_id: o.order_id,
            table_no: o.table_no,
            product_id: o.product_id,
            product_name: o.product_name,
            type_id: o.type_id || 1,
            quantity: Number(o.quantity),
            price: Number(o.price),
            subtotal: Number(o.subtotal || o.price * o.quantity),
            order_date: o.order_date,
            status: o.status || "รอดำเนินการ",
          }));
        } else {
          error.value = data.message || "ไม่สามารถโหลดข้อมูลได้";
        }
      } catch (err) {
        error.value = "เกิดข้อผิดพลาด: " + err.message;
      } finally {
        loading.value = false;
      }
    };

    onMounted(fetchOrders);

    const getCategoryName = (typeId) => {
      const category = categories.value.find(c => c.id === typeId);
      return category ? category.name : 'ไม่ระบุ';
    };

    const filteredOrders = computed(() => {
      let result = orders.value;
      
      if (statusFilter.value) {
        result = result.filter(order => order.status === statusFilter.value);
      }
      
      if (categoryFilter.value) {
        result = result.filter(order => order.type_id === categoryFilter.value);
      }
      
      if (searchKeyword.value) {
        const keyword = searchKeyword.value.toString().toLowerCase();
        result = result.filter(order => {
          if (searchBy.value === "table_no") return order.table_no.toString().toLowerCase().includes(keyword);
          if (searchBy.value === "order_id") return order.order_id.toString().toLowerCase().includes(keyword);
          return true;
        });
      }
      
      return result;
    });

    const totalPages = computed(() => Math.ceil(filteredOrders.value.length / rowsPerPage.value));
    const paginatedOrders = computed(() => {
      const start = (currentPage.value - 1) * rowsPerPage.value;
      return filteredOrders.value.slice(start, start + rowsPerPage.value);
    });

    watch([rowsPerPage, statusFilter, categoryFilter, searchKeyword], () => { 
      currentPage.value = 1; 
    });

    const updateStatus = async (order) => {
      try {
        const res = await fetch("http://localhost:8081/MK_SHOP/php_api/update_order_status.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ 
            order_id: order.order_id, 
            product_id: order.product_id, 
            status: order.status 
          }),
        });
        const data = await res.json();
        if (!data.success) alert("อัปเดตสถานะไม่สำเร็จ: " + (data.message || "เกิดข้อผิดพลาด"));
        await fetchOrders();
      } catch (err) { 
        alert("เกิดข้อผิดพลาด: " + err.message); 
      }
    };

    const deleteOrder = async (order) => {
      if (!confirm(`คุณต้องการลบคำสั่งซื้อ ${order.order_id} หรือไม่?`)) return;
      try {
        const res = await fetch("http://localhost:8081/MK_SHOP/php_api/delete_order.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ 
            order_id: order.order_id, 
            product_id: order.product_id 
          }),
        });
        const data = await res.json();
        if (data.success) {
          alert("ลบคำสั่งซื้อเรียบร้อยแล้ว");
          await fetchOrders();
        } else {
          alert("ลบไม่สำเร็จ: " + (data.message || "เกิดข้อผิดพลาด"));
        }
      } catch (err) {
        alert("เกิดข้อผิดพลาด: " + err.message);
      }
    };

    const deleteAllCancelledOrders = async () => {
      if (!confirm("คุณต้องการลบคำสั่งซื้อที่ยกเลิกทั้งหมดหรือไม่?")) return;
      try {
        const res = await fetch("http://localhost:8081/MK_SHOP/php_api/delete_cancelled_orders.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
        });
        const data = await res.json();
        if (data.success) {
          alert("ลบคำสั่งซื้อที่ยกเลิกทั้งหมดเรียบร้อยแล้ว");
          await fetchOrders();
        } else {
          alert("ลบไม่สำเร็จ: " + (data.message || "เกิดข้อผิดพลาด"));
        }
      } catch (err) {
        alert("เกิดข้อผิดพลาด: " + err.message);
      }
    };

    return {
      orders, 
      categories,
      loading, 
      error, 
      searchKeyword, 
      searchBy, 
      statusFilter,
      categoryFilter,
      currentPage, 
      rowsPerPage, 
      totalPages, 
      paginatedOrders,
      getCategoryName,
      updateStatus, 
      deleteOrder, 
      deleteAllCancelledOrders
    };
  },
};
</script>

<style scoped>
.table { font-size: 0.95rem; }
.badge { font-size: 0.85rem; }
.btn-sm { 
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
}
</style>