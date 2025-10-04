import { useQuery, useMutation, useQueryClient } from 'react-query';
import axios from 'axios';
import { toast } from 'react-hot-toast';

// Fetch users
const fetchUsers = async () => {
  const response = await axios.get('http://localhost:8000/api/users.php');
  if (!response.data.success) {
    throw new Error(response.data.message || 'Failed to fetch users');
  }
  return response.data.data;
};

export const useUsers = () => {
  return useQuery('users', fetchUsers, {
    staleTime: 5 * 60 * 1000, // 5 minutes
    cacheTime: 10 * 60 * 1000, // 10 minutes
    retry: 1,
  });
};

// Validate user
export const useValidateUser = () => {
  const queryClient = useQueryClient();
  
  return useMutation(async (userId) => {
    const response = await axios.post('http://localhost:8000/api/users.php', {
      action: 'validate',
      user_id: userId
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to validate user');
    }
    return response.data;
  }, {
    onSuccess: () => {
      queryClient.invalidateQueries('users');
      toast.success('Utente validato con successo');
    },
    onError: (error) => {
      toast.error(error.message || 'Errore durante la validazione');
    }
  });
};

// Reject user
export const useRejectUser = () => {
  const queryClient = useQueryClient();
  
  return useMutation(async (userId) => {
    const response = await axios.post('http://localhost:8000/api/users.php', {
      action: 'reject',
      user_id: userId
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to reject user');
    }
    return response.data;
  }, {
    onSuccess: () => {
      queryClient.invalidateQueries('users');
      toast.success('Utente rifiutato');
    },
    onError: (error) => {
      toast.error(error.message || 'Errore durante il rifiuto');
    }
  });
};

// Delete user
export const useDeleteUser = () => {
  const queryClient = useQueryClient();
  
  return useMutation(async (userId) => {
    const response = await axios.post('http://localhost:8000/api/users.php', {
      action: 'delete',
      user_id: userId
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to delete user');
    }
    return response.data;
  }, {
    onSuccess: () => {
      queryClient.invalidateQueries('users');
      toast.success('Utente eliminato con successo');
    },
    onError: (error) => {
      toast.error(error.message || 'Errore durante l\'eliminazione');
    }
  });
};