<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salon Fryzjerski dla Psów - Kalendarz</title>
    <script src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js" rel="stylesheet">
</head>
<body>
    <div id="root"></div>

    <script type="text/babel">
        const { useState, useEffect } = React;
        
        // Import Lucide icons
        const { 
            Calendar, Clock, User, Phone, Trash2, Plus, 
            ChevronLeft, ChevronRight, Users, UserCheck 
        } = lucide;

        // API Base URL - adjust this to your server setup
        const API_BASE = '/api';

        const DogGroomingCalendar = () => {
            const [currentUser, setCurrentUser] = useState('');
            const [appointments, setAppointments] = useState([]);
            const [showAddForm, setShowAddForm] = useState(false);
            const [currentDate, setCurrentDate] = useState(new Date());
            const [clientHistory, setClientHistory] = useState(null);
            const [isCheckingClient, setIsCheckingClient] = useState(false);
            const [employees, setEmployees] = useState([]);
            const [loading, setLoading] = useState(false);

            const [newAppointment, setNewAppointment] = useState({
                date: new Date().toISOString().split('T')[0],
                timeStart: '',
                timeEnd: '',
                clientName: '',
                dogName: '',
                phone: '',
                service: '',
                employee: ''
            });

            const [selectedAppointment, setSelectedAppointment] = useState(null);

            const timeSlots = [
                '8:00', '8:30', '9:00', '9:30', '10:00', '10:30', '11:00', '11:30',
                '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30',
                '16:00', '16:30', '17:00', '17:30'
            ];

            // Load employees on component mount
            useEffect(() => {
                loadEmployees();
            }, []);

            // Load appointments when date changes
            useEffect(() => {
                if (currentUser) {
                    loadAppointments();
                }
            }, [currentDate, currentUser]);

            const loadEmployees = async () => {
                try {
                    const response = await fetch(`${API_BASE}/employees.php`);
                    const data = await response.json();
                    if (response.ok) {
                        setEmployees(data.map(emp => emp.name));
                    }
                } catch (error) {
                    console.error('Error loading employees:', error);
                    // Fallback to hardcoded employees
                    setEmployees(['Administrator', 'Wiola', 'Kamila', 'Beata', 'Dawid']);
                }
            };

            const loadAppointments = async () => {
                setLoading(true);
                try {
                    const year = currentDate.getFullYear();
                    const month = currentDate.getMonth();
                    const startDate = new Date(year, month, 1).toISOString().split('T')[0];
                    const endDate = new Date(year, month + 1, 0).toISOString().split('T')[0];
                    
                    const response = await fetch(`${API_BASE}/appointments.php?startDate=${startDate}&endDate=${endDate}`);
                    const data = await response.json();
                    
                    if (response.ok) {
                        setAppointments(data);
                    } else {
                        console.error('Error loading appointments:', data.error);
                    }
                } catch (error) {
                    console.error('Error loading appointments:', error);
                } finally {
                    setLoading(false);
                }
            };

            const handleLogin = (employee) => {
                setCurrentUser(employee);
            };

            const handlePhoneChange = async (phone) => {
                setNewAppointment({...newAppointment, phone});
                setClientHistory(null);
                
                const formattedPhone = phone.replace(/[^\d]/g, '');
                if (formattedPhone.length >= 9) {
                    setIsCheckingClient(true);
                    try {
                        const response = await fetch(`${API_BASE}/client-history.php/${formattedPhone}`);
                        const history = await response.json();
                        
                        if (response.ok) {
                            setClient